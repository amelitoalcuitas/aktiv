<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SendsBookingNotification;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestBookingTrackingController extends Controller
{
    use SendsBookingNotification;

    /**
     * Return booking details for the guest tracking page.
     */
    public function show(string $token): JsonResponse
    {
        $booking = Booking::where('guest_tracking_token', $token)
            ->with(['court.hub', 'court.sports'])
            ->firstOrFail();

        return response()->json([
            'data' => $this->formatBooking($booking),
        ]);
    }

    /**
     * Upload a receipt via the guest tracking token.
     */
    public function uploadReceipt(
        Request $request,
        string $token,
        ImageUploadService $imageUploadService
    ): JsonResponse {
        $booking = Booking::where('guest_tracking_token', $token)
            ->with(['court.hub'])
            ->firstOrFail();

        $request->validate([
            'receipt_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        if ($booking->status !== 'pending_payment') {
            return response()->json([
                'message' => 'Receipt can only be uploaded for bookings awaiting payment.',
            ], 422);
        }

        $result = $imageUploadService->upload($request->file('receipt_image'), 'receipts');

        $booking->update([
            'receipt_image_url'   => $result['url'],
            'receipt_uploaded_at' => now(),
            'status'              => 'payment_sent',
        ]);

        $hub = $booking->court->hub;
        $owner = $hub->owner()->first();
        if ($owner) {
            $this->notifyBookingActivity($owner, $booking, 'receipt_uploaded');
        }

        return response()->json([
            'message' => 'Receipt uploaded. The hub owner will review your payment.',
            'data'    => $this->formatBooking($booking->fresh(['court.hub', 'court.sports'])),
        ]);
    }

    /**
     * Cancel a booking via the guest tracking token.
     */
    public function cancel(string $token): JsonResponse
    {
        $booking = Booking::where('guest_tracking_token', $token)
            ->with(['court.hub'])
            ->firstOrFail();

        if (in_array($booking->status, ['cancelled', 'completed'])) {
            return response()->json([
                'message' => 'This booking cannot be cancelled.',
            ], 422);
        }

        if (now()->greaterThan($booking->end_time)) {
            return response()->json([
                'message' => 'This booking has already ended and cannot be cancelled.',
            ], 422);
        }

        $booking->update([
            'status'       => 'cancelled',
            'cancelled_by' => 'user',
        ]);

        return response()->json([
            'message' => 'Booking cancelled.',
            'data'    => $this->formatBooking($booking->fresh(['court.hub', 'court.sports'])),
        ]);
    }

    private function formatBooking(Booking $booking): array
    {
        $court = $booking->court;
        $hub = $court->hub;

        return [
            'id'                  => $booking->id,
            'booking_code'        => $booking->booking_code,
            'status'              => $booking->status,
            'guest_name'          => $booking->guest_name,
            'start_time'          => $booking->start_time->toIso8601String(),
            'end_time'            => $booking->end_time->toIso8601String(),
            'expires_at'          => $booking->expires_at?->toIso8601String(),
            'total_price'         => $booking->total_price,
            'payment_method'      => $booking->payment_method,
            'receipt_image_url'   => $booking->receipt_image_url,
            'receipt_uploaded_at' => $booking->receipt_uploaded_at?->toIso8601String(),
            'payment_note'        => $booking->payment_note,
            'court'               => [
                'id'   => $court->id,
                'name' => $court->name,
            ],
            'hub' => [
                'id'   => $hub->id,
                'name' => $hub->name,
                'slug' => $hub->slug,
            ],
        ];
    }
}
