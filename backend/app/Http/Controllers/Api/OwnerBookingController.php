<?php

namespace App\Http\Controllers\Api;

use App\Events\BookingSlotUpdated;
use App\Http\Controllers\Concerns\SendsBookingNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\RejectBookingRequest;
use App\Http\Requests\Booking\WalkInBookingRequest;
use App\Mail\BookingStatusUpdate;
use App\Mail\OwnerCancelledBookingNotification;
use App\Mail\WalkInBookingConfirmation;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OwnerBookingController extends Controller
{
    use SendsBookingNotification;
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
            $query->where('end_time', '>=', Carbon::parse($request->date_from, 'Asia/Manila')->startOfDay()->utc());
        }

        if ($request->filled('date_to')) {
            $query->where('start_time', '<=', Carbon::parse($request->date_to, 'Asia/Manila')->endOfDay()->utc());
        }

        $bookings = $query->get()->map(fn (Booking $b) => $this->formatBooking($b));

        return response()->json(['data' => $bookings]);
    }

    /**
     * Return a single booking belonging to the given hub.
     */
    public function show(Hub $hub, Booking $booking, Request $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($booking, $hub);

        return response()->json([
            'data' => $this->formatBooking($booking->load(['court', 'bookedBy'])),
        ]);
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
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $court = Court::findOrFail($validated['court_id']);
        abort_if($court->hub_id !== $hub->id, 422);

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = Carbon::parse($validated['end_time']);

        // Check conflicts excluding current booking and expired bookings
        $conflict = Booking::where('court_id', $court->id)
            ->where('id', '!=', $booking->id)
            ->whereNotIn('status', ['cancelled'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
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
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_price' => $totalPrice,
        ]);

        broadcast(new BookingSlotUpdated(
            hubId: $hub->id,
            courtId: $booking->court_id,
            status: $booking->status,
        ));

        $fresh = $booking->fresh(['court', 'bookedBy']);

        if ($fresh->guest_email) {
            Mail::to($fresh->guest_email)->send(new BookingStatusUpdate($fresh, $hub, 'booking_updated'));
        }

        return response()->json([
            'message' => 'Booking updated successfully.',
            'data' => $this->formatBooking($fresh),
        ]);
    }

    /**
     * Confirm a payment receipt (payment_sent → confirmed).
     */
    public function confirm(Hub $hub, Booking $booking, Request $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($booking, $hub);

        $confirmableStatuses = ['payment_sent', 'pending_payment'];
        if (! in_array($booking->status, $confirmableStatuses)) {
            return response()->json([
                'message' => 'This booking cannot be confirmed.',
            ], 422);
        }

        $booking->update([
            'status' => 'confirmed',
            'payment_confirmed_by' => $request->user()->id,
            'payment_confirmed_at' => now(),
        ]);

        $booking->load('court.hub');

        // Notify the booker
        if ($booking->bookedBy) {
            $this->notifyBookingActivity($booking->bookedBy, $booking, 'booking_confirmed');
        } elseif ($booking->guest_email) {
            Mail::to($booking->guest_email)->send(new BookingStatusUpdate($booking, $hub, 'booking_confirmed'));
        }

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

        $rejectableStatuses = ['payment_sent', 'pending_payment'];
        if (! in_array($booking->status, $rejectableStatuses)) {
            return response()->json([
                'message' => 'This booking cannot be rejected.',
            ], 422);
        }

        $booking->update([
            'status' => 'pending_payment',
            'payment_note' => $request->payment_note,
            'receipt_image_url' => null,
            'receipt_uploaded_at' => null,
            'expires_at' => $this->resolveExpiresAt($booking->payment_method ?? 'digital_bank', $booking->start_time),
        ]);

        $booking->load('court.hub');

        // Notify the booker
        if ($booking->bookedBy) {
            $this->notifyBookingActivity($booking->bookedBy, $booking, 'booking_rejected');
        } elseif ($booking->guest_email) {
            Mail::to($booking->guest_email)->send(new BookingStatusUpdate($booking, $hub, 'booking_rejected'));
        }

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

        $booking->load('court.hub');

        if ($booking->bookedBy) {
            $this->notifyBookingActivity($booking->bookedBy, $booking, 'booking_cancelled');
        } elseif ($booking->guest_email) {
            Mail::to($booking->guest_email)->send(new BookingStatusUpdate($booking, $hub, 'booking_cancelled'));
            broadcast(new BookingSlotUpdated(
                hubId: $hub->id,
                courtId: $booking->court_id,
                status: $booking->status,
            ));
        } else {
            // No user or guest email — still broadcast the slot change
            broadcast(new BookingSlotUpdated(
                hubId: $hub->id,
                courtId: $booking->court_id,
                status: $booking->status,
            ));
        }

        // Always notify the hub owner so they have a record of the cancellation
        Mail::to($request->user()->email)
            ->send(new OwnerCancelledBookingNotification($booking, $hub));

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

        // Conflict detection — same logic as self-booked, excluding expired bookings
        $conflict = Booking::where('court_id', $court->id)
            ->whereNotIn('status', ['cancelled'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
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
            'start_time' => $startTime,
            'end_time' => $endTime,
            'session_type' => $request->session_type ?? 'private',
            'status' => 'confirmed',
            'booking_source' => 'owner_added',
            'guest_name' => $request->guest_name ?? null,
            'guest_phone' => $request->guest_phone ?? null,
            'guest_email' => $request->guest_email ?? null,
            'total_price' => $totalPrice,
            'expires_at' => null,
        ]);

        broadcast(new BookingSlotUpdated(
            hubId: $hub->id,
            courtId: $booking->court_id,
            status: $booking->status,
        ));

        if ($request->filled('guest_email')) {
            $trackingToken = (string) Str::uuid();
            $booking->update(['guest_tracking_token' => $trackingToken]);
            Mail::to($request->guest_email)
                ->send(new WalkInBookingConfirmation($booking, $hub, $court->name, $trackingToken));
        }

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

    private function resolveExpiresAt(string $paymentMethod, Carbon $startTime): Carbon
    {
        if ($paymentMethod === 'pay_on_site') {
            return $startTime->copy();
        }

        // digital_bank: 1 hour from now, capped at start_time
        return now()->addHour()->min($startTime);
    }
}
