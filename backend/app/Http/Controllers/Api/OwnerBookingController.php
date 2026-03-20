<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\RejectBookingRequest;
use App\Http\Requests\Booking\WalkInBookingRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerBookingController extends Controller
{
    /**
     * List all bookings for a hub the authenticated user owns.
     * Supports optional filters: ?status, ?court_id, ?date_from, ?date_to
     */
    public function index(Hub $hub, Request $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);

        $courtIds = $hub->courts()->pluck('id');

        $query = Booking::whereIn('court_id', $courtIds)
            ->with([
                'court:id,name,hub_id',
                'bookedBy:id,name,email,phone,avatar_url',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('court_id')) {
            abort_if(! $courtIds->contains($request->court_id), 422);
            $query->where('court_id', $request->court_id);
        }

        if ($request->filled('date_from')) {
            $query->where('start_time', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('start_time', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $bookings = $query->get()->map(fn (Booking $b) => $this->formatBooking($b));

        return response()->json(['data' => $bookings]);
    }

    /**
     * Update an existing booking (used by owner modal).
     */
    public function update(Hub $hub, Booking $booking, Request $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($booking, $hub);

        $validated = $request->validate([
            'court_id' => 'required|exists:courts,id',
            'sport' => 'required|string|max:50',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $court = Court::findOrFail($validated['court_id']);
        abort_if($court->hub_id !== $hub->id, 422);

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = Carbon::parse($validated['end_time']);

        // Check conflicts excluding current booking
        $conflict = Booking::where('court_id', $court->id)
            ->where('id', '!=', $booking->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'This time slot is already booked on the selected court.',
            ], 409);
        }

        $hours = $startTime->diffInMinutes($endTime) / 60;
        $pricePerHour = (float) $court->price_per_hour;
        $totalPrice = $pricePerHour > 0 ? round($pricePerHour * $hours, 2) : null;

        $booking->update([
            'court_id' => $court->id,
            'sport' => $validated['sport'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_price' => $totalPrice,
        ]);

        return response()->json([
            'message' => 'Booking updated successfully.',
            'data' => $this->formatBooking($booking->fresh(['court', 'bookedBy'])),
        ]);
    }

    /**
     * Confirm a payment receipt (payment_sent → confirmed).
     */
    public function confirm(Hub $hub, Booking $booking, Request $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($booking, $hub);

        if ($booking->status !== 'payment_sent') {
            return response()->json([
                'message' => 'Only bookings with status payment_sent can be confirmed.',
            ], 422);
        }

        $booking->update([
            'status' => 'confirmed',
            'payment_confirmed_by' => $request->user()->id,
            'payment_confirmed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Booking confirmed.',
            'data' => $this->formatBooking($booking->fresh(['court', 'bookedBy'])),
        ]);
    }

    /**
     * Reject a receipt (payment_sent → pending_payment, with reason).
     * Resets the 1-hour expiry window.
     */
    public function reject(Hub $hub, Booking $booking, RejectBookingRequest $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($booking, $hub);

        if ($booking->status !== 'payment_sent') {
            return response()->json([
                'message' => 'Only bookings with status payment_sent can be rejected.',
            ], 422);
        }

        $booking->update([
            'status' => 'pending_payment',
            'payment_note' => $request->payment_note,
            'receipt_image_url' => null,
            'receipt_uploaded_at' => null,
            'expires_at' => now()->addHour(),
        ]);

        return response()->json([
            'message' => 'Booking rejected. User can re-upload their receipt.',
            'data' => $this->formatBooking($booking->fresh(['court', 'bookedBy'])),
        ]);
    }

    /**
     * Cancel a booking as hub owner.
     */
    public function cancel(Hub $hub, Booking $booking, Request $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($booking, $hub);

        if (in_array($booking->status, ['cancelled', 'completed'])) {
            return response()->json([
                'message' => 'This booking cannot be cancelled.',
            ], 422);
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_by' => 'owner',
        ]);

        return response()->json([
            'message' => 'Booking cancelled.',
            'data' => $this->formatBooking($booking->fresh(['court', 'bookedBy'])),
        ]);
    }

    /**
     * Create an owner walk-in booking — instantly confirmed, no receipt required.
     */
    public function walkIn(Hub $hub, WalkInBookingRequest $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);

        $court = Court::findOrFail($request->court_id);
        abort_if($court->hub_id !== $hub->id, 422);

        $startTime = Carbon::parse($request->start_time);
        $endTime = Carbon::parse($request->end_time);

        // Conflict detection — same logic as self-booked
        $conflict = Booking::where('court_id', $court->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'This time slot is already booked. Please choose a different time.',
            ], 409);
        }

        $hours = $startTime->diffInMinutes($endTime) / 60;
        $pricePerHour = (float) $court->price_per_hour;
        $totalPrice = $pricePerHour > 0 ? round($pricePerHour * $hours, 2) : null;

        $booking = Booking::create([
            'court_id' => $court->id,
            'booked_by' => $request->booked_by ?? null,
            'created_by' => $request->user()->id,
            'sport' => $request->sport,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'session_type' => $request->session_type ?? 'private',
            'status' => 'confirmed',
            'booking_source' => 'owner_added',
            'guest_name' => $request->guest_name ?? null,
            'guest_phone' => $request->guest_phone ?? null,
            'total_price' => $totalPrice,
            'expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Walk-in booking created.',
            'data' => $this->formatBooking($booking->load(['court', 'bookedBy'])),
        ], 201);
    }

    /**
     * Search registered users by name, email, or phone — for walk-in picker.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:1|max:100']);

        $q = '%'.trim($request->q).'%';

        $users = User::where(function ($query) use ($q) {
            $query->where('name', 'ilike', $q)
                ->orWhere('email', 'ilike', $q)
                ->orWhere('phone', 'ilike', $q);
        })
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'avatar_url']);

        return response()->json(['data' => $users]);
    }

    /**
     * Look up a booking by booking_code for on-site verification.
     * Returns the booking with customer info so the owner can confirm or reject.
     */
    public function verifyByCode(Hub $hub, string $code, Request $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);

        $courtIds = $hub->courts()->pluck('id');

        $booking = Booking::query()
            ->whereIn('court_id', $courtIds)
            ->where('booking_code', strtoupper(trim($code)))
            ->with(['court:id,name,hub_id', 'bookedBy:id,name,email,phone,avatar_url'])
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'No booking found with that code.'], 404);
        }

        return response()->json(['data' => $this->formatBooking($booking)]);
    }

    // ── Private helpers ──────────────────────────────────────────

    private function assertBelongsToHub(Booking $booking, Hub $hub): void
    {
        $courtIds = $hub->courts()->pluck('id');
        abort_if(! $courtIds->contains($booking->court_id), 404);
    }

    private function formatBooking(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'court_id' => $booking->court_id,
            'court' => $booking->court ? [
                'id' => $booking->court->id,
                'name' => $booking->court->name,
                'hub_id' => $booking->court->hub_id,
            ] : null,
            'booked_by' => $booking->booked_by,
            'booked_by_user' => $booking->bookedBy ? [
                'id' => $booking->bookedBy->id,
                'name' => $booking->bookedBy->name,
                'email' => $booking->bookedBy->email,
                'phone' => $booking->bookedBy->phone,
                'avatar_url' => $booking->bookedBy->avatar_url,
            ] : null,
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'guest_phone' => $booking->guest_phone,
            'sport' => $booking->sport,
            'start_time' => $booking->start_time->toIso8601String(),
            'end_time' => $booking->end_time->toIso8601String(),
            'session_type' => $booking->session_type,
            'status' => $booking->status,
            'booking_source' => $booking->booking_source,
            'total_price' => $booking->total_price,
            'receipt_image_url' => $booking->receipt_image_url,
            'receipt_uploaded_at' => $booking->receipt_uploaded_at?->toIso8601String(),
            'payment_note' => $booking->payment_note,
            'payment_confirmed_by' => $booking->payment_confirmed_by,
            'payment_confirmed_at' => $booking->payment_confirmed_at?->toIso8601String(),
            'expires_at' => $booking->expires_at?->toIso8601String(),
            'cancelled_by' => $booking->cancelled_by,
            'created_at' => $booking->created_at->toIso8601String(),
        ];
    }
}
