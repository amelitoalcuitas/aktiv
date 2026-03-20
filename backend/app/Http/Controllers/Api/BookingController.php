<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\UploadReceiptRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use Illuminate\Http\Request;
use App\Services\ImageUploadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    /**
     * List non-cancelled bookings for ALL courts in a hub in a single request.
     * Public — no auth required. Returns minimal data grouped by court_id.
     * Supports optional date_from / date_to query params (YYYY-MM-DD).
     */
    public function hubIndex(Request $request, Hub $hub): JsonResponse
    {
        $courtIds = $hub->courts()->pluck('id');

        $query = Booking::whereIn('court_id', $courtIds)
            ->whereNotIn('status', ['cancelled']);

        if ($request->filled('date_from')) {
            $query->where('end_time', '>=', Carbon::parse($request->date_from)->startOfDay());
        } else {
            $query->where('end_time', '>=', now()->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('start_time', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $bookings = $query->orderBy('start_time')->get();
        $userId = auth()->id();

        $grouped = [];
        foreach ($bookings as $b) {
            $grouped[$b->court_id][] = [
                'id' => $b->id,
                'start_time' => $b->start_time->toIso8601String(),
                'end_time' => $b->end_time->toIso8601String(),
                'session_type' => $b->session_type,
                'status' => $b->status,
                'is_own' => $userId !== null && (int) $b->booked_by === (int) $userId,
            ];
        }

        return response()->json(['data' => $grouped]);
    }

    /**
     * List non-cancelled upcoming bookings for a court (used to render the calendar).
     * Public — no auth required. Returns minimal data; no personal info exposed.
     */
    public function index(Hub $hub, Court $court): JsonResponse
    {
        abort_if($court->hub_id !== $hub->id, 404);

        $bookings = Booking::where('court_id', $court->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('end_time', '>=', now()->startOfDay())
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
                'booking_code' => $booking->booking_code,
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

    /**
     * Upload a GCash/bank-transfer receipt for a pending_payment booking.
     * Only the customer who made the booking may upload their receipt.
     */
    public function uploadReceipt(
        UploadReceiptRequest $request,
        Hub $hub,
        Court $court,
        Booking $booking,
        ImageUploadService $imageUploadService
    ): JsonResponse {
        abort_if($court->hub_id !== $hub->id, 404);
        abort_if($booking->court_id !== $court->id, 404);
        abort_if($booking->booked_by !== $request->user()->id, 403);

        if ($booking->status !== 'pending_payment') {
            return response()->json([
                'message' => 'Receipt can only be uploaded for bookings awaiting payment.',
            ], 422);
        }

        $result = $imageUploadService->upload($request->file('receipt_image'), 'receipts');

        $booking->update([
            'receipt_image_url' => $result['url'],
            'receipt_uploaded_at' => now(),
            'status' => 'payment_sent',
        ]);

        return response()->json([
            'message' => 'Receipt uploaded. The hub owner will review your payment.',
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'receipt_image_url' => $booking->receipt_image_url,
                'receipt_uploaded_at' => $booking->receipt_uploaded_at->toIso8601String(),
            ],
        ]);
    }
}
