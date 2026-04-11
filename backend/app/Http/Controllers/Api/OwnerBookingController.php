<?php

namespace App\Http\Controllers\Api;

use App\Events\BookingSlotUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\RejectBookingRequest;
use App\Http\Requests\Booking\WalkInBookingRequest;
use App\Http\Resources\OwnerBookingResource;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\HubEvent;
use App\Models\User;
use App\Services\BookingNotificationService;
use App\Support\HubTimezone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OwnerBookingController extends Controller
{
    public function __construct(private BookingNotificationService $notifications) {}
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
                'court.hub:id,timezone',
                'bookedBy:id,first_name,last_name,email,contact_number,avatar_url',
                'openPlaySession' => fn ($q) => $q->withCount([
                    'participants as participants_count' => fn ($q) => $q->where('payment_status', '!=', 'cancelled'),
                    'participants as confirmed_participants_count' => fn ($q) => $q->where('payment_status', 'confirmed'),
                ])->with([
                    'participants' => fn ($q) => $q
                        ->with('user:id,first_name,last_name,email,contact_number,avatar_url')
                        ->orderBy('joined_at')
                        ->orderBy('created_at'),
                ]),
            ])
            ->orderByDesc('created_at');

        $timezone = $hub->timezone_name;

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('court_id')) {
            abort_if(! $courtIds->contains($request->court_id), 422);
            $query->where('court_id', $request->court_id);
        }

        if ($request->filled('date_from')) {
            $query->where('end_time', '>=', HubTimezone::startOfDayUtc($request->date_from, $timezone));
        }

        if ($request->filled('date_to')) {
            $query->where('start_time', '<=', HubTimezone::endOfDayUtc($request->date_to, $timezone));
        }

        $bookings = OwnerBookingResource::collection($query->get())->resolve();

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
            'data' => OwnerBookingResource::make($booking->load(['court.hub', 'bookedBy']))->resolve(),
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

        // Closure check: reject if an active closure event covers this court and time window
        $closureEvent = $this->findClosureEvent($hub, $court, $startTime, $endTime);
        if ($closureEvent) {
            return response()->json([
                'message' => "This court is unavailable: {$closureEvent->title}",
            ], 422);
        }

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

        $fresh = $booking->fresh(['court.hub', 'bookedBy']);
        $this->notifications->notifyBookingUpdated($fresh);

        return response()->json([
            'message' => 'Booking updated successfully.',
            'data' => OwnerBookingResource::make($fresh)->resolve(),
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
        $this->notifications->notifyBookingConfirmed($booking);

        return response()->json([
            'message' => 'Booking confirmed.',
            'data' => OwnerBookingResource::make($booking->fresh(['court.hub', 'bookedBy']))->resolve(),
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
        $this->notifications->notifyBookingRejected($booking);

        return response()->json([
            'message' => 'Booking rejected. User can re-upload their receipt.',
            'data' => OwnerBookingResource::make($booking->fresh(['court.hub', 'bookedBy']))->resolve(),
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

        $booking->load('court.hub.owner');
        $this->notifications->notifyBookingCancelled($booking, cancelledBy: 'owner');

        return response()->json([
            'message' => 'Booking cancelled.',
            'data' => OwnerBookingResource::make($booking->fresh(['court.hub', 'bookedBy']))->resolve(),
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

        // Closure check: reject if an active closure event covers this court and time window
        $closureEvent = $this->findClosureEvent($hub, $court, $startTime, $endTime);
        if ($closureEvent) {
            return response()->json([
                'message' => "This court is unavailable: {$closureEvent->title}",
            ], 422);
        }

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
            $booking->guest_tracking_token = $trackingToken;
            $booking->load('court.hub');
            $this->notifications->notifyWalkInBooking($booking);
        }

        return response()->json([
            'message' => 'Walk-in booking created.',
            'data' => OwnerBookingResource::make($booking->load(['court', 'bookedBy']))->resolve(),
        ], 201);
    }

    /**
     * Search registered users by name, email, or phone — for walk-in picker.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:1|max:100']);

        $q = '%'.trim($request->q).'%';

        $users = User::where('role', '!=', \App\Enums\UserRole::SuperAdmin)->where(function ($query) use ($q) {
            $query->where('first_name', 'ilike', $q)
                ->orWhere('last_name', 'ilike', $q)
                ->orWhere('email', 'ilike', $q)
                ->orWhere('contact_number', 'ilike', $q);
        })
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email', 'contact_number', 'avatar_url']);

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
            ->with(['court:id,name,hub_id', 'court.hub:id,timezone', 'bookedBy:id,first_name,last_name,email,contact_number,avatar_url'])
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'No booking found with that code.'], 404);
        }

        return response()->json(['data' => OwnerBookingResource::make($booking)->resolve()]);
    }

    // ── Private helpers ──────────────────────────────────────────

    private function assertBelongsToHub(Booking $booking, Hub $hub): void
    {
        $courtIds = $hub->courts()->pluck('id');
        abort_if(! $courtIds->contains($booking->court_id), 404);
    }

    private function findClosureEvent(Hub $hub, Court $court, Carbon $startTime, Carbon $endTime): ?HubEvent
    {
        return HubEvent::where('hub_id', $hub->id)
            ->where('event_type', 'closure')
            ->where('is_active', true)
            ->get()
            ->first(fn (HubEvent $e) => $e->overlapsWindow($startTime, $endTime) && $e->appliesToCourt($court->id));
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
