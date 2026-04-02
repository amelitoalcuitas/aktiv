<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HubWebsite;
use App\Models\OpenPlayParticipant;
use App\Services\ImageUploadService;
use App\Services\OpenPlayNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestOpenPlayTrackingController extends Controller
{
    public function __construct(private OpenPlayNotificationService $notifications) {}

    public function show(string $token): JsonResponse
    {
        $participant = $this->findParticipant($token);

        return response()->json([
            'data' => $this->formatParticipant($participant),
        ]);
    }

    public function uploadReceipt(
        Request $request,
        string $token,
        ImageUploadService $imageUploadService
    ): JsonResponse {
        $participant = $this->findParticipant($token);
        $session = $participant->openPlaySession;
        $booking = $session->booking;

        $request->validate([
            'receipt_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        if ($participant->payment_method !== 'digital_bank') {
            return response()->json([
                'message' => 'Receipt upload is only available for digital bank payment.',
            ], 422);
        }

        if ($participant->payment_status !== 'pending_payment') {
            return response()->json([
                'message' => 'Receipt can only be uploaded when payment is pending.',
            ], 422);
        }

        if ($participant->expires_at && now()->greaterThanOrEqualTo($participant->expires_at)) {
            return response()->json([
                'message' => 'This open play join has expired and can no longer receive a receipt.',
            ], 422);
        }

        if (now()->greaterThanOrEqualTo($booking->end_time)) {
            return response()->json([
                'message' => 'This open play session has already ended.',
            ], 422);
        }

        $result = $imageUploadService->upload($request->file('receipt_image'), 'receipts');

        $participant->update([
            'receipt_image_url'   => $result['url'],
            'receipt_uploaded_at' => now(),
            'payment_status'      => 'payment_sent',
        ]);

        $session->loadMissing('booking.court.hub.owner');
        $participant->setRelation('openPlaySession', $session);
        $this->notifications->notifyReceiptUploaded($participant, $session);

        return response()->json([
            'message' => 'Receipt uploaded. The hub owner will review your payment.',
            'data' => $this->formatParticipant($participant->fresh([
                'openPlaySession.booking.court.hub.contactNumbers',
                'openPlaySession.booking.court.hub.websites',
            ])),
        ]);
    }

    public function cancel(string $token): JsonResponse
    {
        $participant = $this->findParticipant($token);
        $session = $participant->openPlaySession;
        $booking = $session->booking;

        if (! in_array($participant->payment_status, ['pending_payment', 'payment_sent'], true)) {
            return response()->json([
                'message' => 'This open play join cannot be cancelled.',
            ], 422);
        }

        if (now()->greaterThanOrEqualTo($booking->end_time)) {
            return response()->json([
                'message' => 'This open play session has already ended and can no longer be cancelled.',
            ], 422);
        }

        $participant->update([
            'payment_status' => 'cancelled',
            'cancelled_by'   => 'user',
        ]);

        $session->recalculateStatus();
        $session->loadMissing('booking.court.hub.owner');
        $participant->setRelation('openPlaySession', $session);
        $this->notifications->notifyParticipantCancelled($participant, $session, 'user');

        return response()->json([
            'message' => 'Open play join cancelled.',
            'data' => $this->formatParticipant($participant->fresh([
                'openPlaySession.booking.court.hub.contactNumbers',
                'openPlaySession.booking.court.hub.websites',
            ])),
        ]);
    }

    private function findParticipant(string $token): OpenPlayParticipant
    {
        return OpenPlayParticipant::where('guest_tracking_token', $token)
            ->with([
                'openPlaySession.booking.court.hub.contactNumbers',
                'openPlaySession.booking.court.hub.websites',
            ])
            ->firstOrFail();
    }

    private function formatParticipant(OpenPlayParticipant $participant): array
    {
        $session = $participant->openPlaySession;
        $booking = $session->booking;
        $court = $booking->court;
        $hub = $court->hub;

        return [
            'id'                   => $participant->id,
            'open_play_session_id' => $session->id,
            'guest_name'           => $participant->guest_name,
            'guest_email'          => $participant->guest_email,
            'status'               => $participant->payment_status,
            'payment_method'       => $participant->payment_method,
            'price_per_player'     => $session->price_per_player,
            'receipt_image_url'    => $participant->receipt_image_url,
            'receipt_uploaded_at'  => $participant->receipt_uploaded_at?->toIso8601String(),
            'payment_note'         => $participant->payment_note,
            'expires_at'           => $participant->expires_at?->toIso8601String(),
            'cancelled_by'         => $participant->cancelled_by,
            'joined_at'            => $participant->joined_at?->toIso8601String(),
            'title'                => $session->title,
            'description'          => $session->notes,
            'notes'                => $session->notes,
            'sport'                => $session->sport ?? $booking->sport ?? 'Open Play',
            'start_time'           => $booking->start_time->toIso8601String(),
            'end_time'             => $booking->end_time->toIso8601String(),
            'court'                => [
                'id'   => $court->id,
                'name' => $court->name,
            ],
            'hub' => [
                'id'       => $hub->id,
                'username' => $hub->username,
                'name'     => $hub->name,
                'phones'   => $hub->contactNumbers->pluck('number')->values(),
                'websites' => $hub->websites->map(fn (HubWebsite $website): array => [
                    'platform' => $website->platform,
                    'url'      => $website->url,
                ])->values(),
            ],
        ];
    }
}
