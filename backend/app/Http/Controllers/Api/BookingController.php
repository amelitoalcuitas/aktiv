<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    /**
     * List non-cancelled upcoming bookings for a court (used to render the calendar).
     * Public — no auth required. Returns minimal data; no personal info exposed.
     */
    public function index(Hub $hub, Court $court): JsonResponse
    {
        abort_if($court->hub_id !== $hub->id, 404);

        $bookings = Booking::where('court_id', $court->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('end_time', '>', now())
            ->orderBy('start_time')
            ->get();

        $userId = auth()->id();

        $data = $bookings->map(fn (Booking $b) => [
            'id' => $b->id,
            'start_time' => $b->start_time->toIso8601String(),
            'end_time' => $b->end_time->toIso8601String(),
            'session_type' => $b->session_type,
            'status' => $b->status,
            'is_own' => $userId !== null && (int) $b->booked_by === (int) $userId,
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Create a self-booked booking. Requires authentication.
     * Slot is immediately blocked as pending_payment with a 1-hour expiry.
     */
    public function store(StoreBookingRequest $request, Hub $hub, Court $court): JsonResponse
    {
        abort_if($court->hub_id !== $hub->id, 404);

        $startTime = Carbon::parse($request->start_time);
        $endTime = Carbon::parse($request->end_time);

        // Conflict detection: any non-cancelled booking whose interval overlaps
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

        // Total price = hours × price_per_hour (stored for future payment integration)
        $hours = $startTime->diffInMinutes($endTime) / 60;
        $pricePerHour = (float) $court->price_per_hour;
        $totalPrice = $pricePerHour > 0 ? round($pricePerHour * $hours, 2) : null;

        $booking = Booking::create([
            'court_id' => $court->id,
            'booked_by' => $request->user()->id,
            'created_by' => $request->user()->id,
            'sport' => $request->sport,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'session_type' => $request->session_type,
            'status' => 'pending_payment',
            'booking_source' => 'self_booked',
            'total_price' => $totalPrice,
            'expires_at' => now()->addHour(),
        ]);

        return response()->json([
            'message' => 'Booking created successfully.',
            'data' => [
                'id' => $booking->id,
                'court_id' => $booking->court_id,
                'sport' => $booking->sport,
                'start_time' => $booking->start_time->toIso8601String(),
                'end_time' => $booking->end_time->toIso8601String(),
                'session_type' => $booking->session_type,
                'status' => $booking->status,
                'booking_source' => $booking->booking_source,
                'total_price' => $booking->total_price,
                'expires_at' => $booking->expires_at->toIso8601String(),
                'created_at' => $booking->created_at->toIso8601String(),
            ],
        ], 201);
    }
}
