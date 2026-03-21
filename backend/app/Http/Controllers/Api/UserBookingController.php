<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserBookingResource;
use App\Models\Booking;
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
            ->orderByDesc('start_time');

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

        // Bookings sorted start_time DESC: count those with a later start_time
        $position = Booking::query()
            ->where('booked_by', $userId)
            ->where('start_time', '>', $booking->start_time)
            ->count();

        // Tie-break: same start_time, higher id sorts first in default Eloquent order
        $ties = Booking::query()
            ->where('booked_by', $userId)
            ->where('start_time', $booking->start_time)
            ->where('id', '>', $booking->id)
            ->count();

        $page = (int) floor(($position + $ties) / $perPage) + 1;

        return response()->json(['page' => $page]);
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
