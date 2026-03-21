<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserBookingResource;
use App\Models\Booking;
use App\Models\BookingReviewSkip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserBookingController extends Controller
{
    /**
     * List the authenticated user's bookings, newest first.
     * Supports optional filter: ?status=pending_payment (or any BookingStatus value)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Booking::query()
            ->where('booked_by', $request->user()->id)
            ->with(['court:id,name,hub_id', 'court.hub:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->paginate(10);

        return UserBookingResource::collection($bookings);
    }

    /**
     * Return the page number (1-indexed, 10/page) where a specific booking appears
     * in the user's full booking list (ordered by start_time DESC).
     */
    public function pageOf(Request $request): JsonResponse
    {
        $request->validate(['booking_id' => ['required', 'integer']]);

        $userId  = $request->user()->id;
        $perPage = 10;

        $booking = Booking::query()
            ->where('id', $request->booking_id)
            ->where('booked_by', $userId)
            ->firstOrFail();

        // Bookings sorted created_at DESC: count those with a later created_at
        $position = Booking::query()
            ->where('booked_by', $userId)
            ->where('created_at', '>', $booking->created_at)
            ->count();

        // Tie-break: same created_at, higher id sorts first in default Eloquent order
        $ties = Booking::query()
            ->where('booked_by', $userId)
            ->where('created_at', $booking->created_at)
            ->where('id', '>', $booking->id)
            ->count();

        $page = (int) floor(($position + $ties) / $perPage) + 1;

        return response()->json(['page' => $page]);
    }

    /**
     * Return all unreviewed ended bookings for the authenticated user (one per hub, most recent).
     *
     * For local testing, pass ?test_booking_id=<id> to force a specific booking.
     */
    public function pendingReview(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Dev shortcut: force a specific booking for testing
        if (app()->environment('local') && $request->filled('test_booking_id')) {
            $booking = Booking::query()
                ->where('id', $request->integer('test_booking_id'))
                ->where('booked_by', $userId)
                ->whereIn('status', ['confirmed', 'completed'])
                ->whereDoesntHave('court.hub.ratings', fn ($q) => $q->where('user_id', $userId))
                ->whereDoesntHave('reviewSkip', fn ($q) => $q->where('user_id', $userId))
                ->with(['court:id,name,hub_id', 'court.hub:id,name,cover_image_url'])
                ->first();

            return response()->json(['bookings' => $booking ? UserBookingResource::collection(collect([$booking])) : []]);
        }

        $bookings = Booking::query()
            ->where('booked_by', $userId)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('end_time', '<', now())
            ->whereDoesntHave('court.hub.ratings', fn ($q) => $q->where('user_id', $userId))
            ->whereDoesntHave('reviewSkip', fn ($q) => $q->where('user_id', $userId))
            ->with(['court:id,name,hub_id', 'court.hub:id,name,cover_image_url'])
            ->orderByDesc('end_time')
            ->get()
            ->unique(fn ($b) => $b->court->hub_id)
            ->values();

        return response()->json(['bookings' => UserBookingResource::collection($bookings)]);
    }

    /**
     * Permanently skip the review prompt for a specific booking.
     */
    public function skipReview(Request $request): JsonResponse
    {
        $request->validate(['booking_id' => ['required', 'integer', 'exists:bookings,id']]);

        BookingReviewSkip::query()->firstOrCreate([
            'user_id'    => $request->user()->id,
            'booking_id' => $request->booking_id,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Cancel the user's own booking (only if pending_payment or payment_sent).
     */
    public function cancel(Booking $booking, Request $request): JsonResponse
    {
        abort_if($booking->booked_by !== $request->user()->id, 403);

        abort_unless(
            in_array($booking->status, ['pending_payment', 'payment_sent'], true),
            422,
            'This booking cannot be cancelled.'
        );

        $booking->update([
            'status'       => 'cancelled',
            'cancelled_by' => 'user',
        ]);

        return response()->json(['data' => new UserBookingResource($booking->load(['court:id,name,hub_id', 'court.hub:id,name']))]);
    }
}
