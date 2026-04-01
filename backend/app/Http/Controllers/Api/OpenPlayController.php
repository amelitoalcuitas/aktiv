<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpenPlay\JoinOpenPlaySessionRequest;
use App\Http\Requests\OpenPlay\SendGuestVerificationRequest;
use App\Http\Resources\OpenPlayParticipantResource;
use App\Http\Resources\OpenPlaySessionResource;
use App\Models\Hub;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlaySession;
use App\Services\BookingNotificationService;
use App\Services\ImageUploadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OpenPlayController extends Controller
{
    public function __construct(
        private BookingNotificationService $notifications
    ) {}

    /**
     * List upcoming open play sessions for a hub.
     * Public — no auth required.
     */
    public function index(Hub $hub, Request $request): JsonResponse
    {
        $courtIds = $hub->courts()->pluck('id');
        $user = $request->user('sanctum');

        $sessions = OpenPlaySession::whereIn('status', ['open', 'full'])
            ->whereHas('booking', fn ($q) => $q
                ->whereIn('court_id', $courtIds)
                ->where('status', 'confirmed')
                ->where('end_time', '>', now())
            )
            ->with(['booking.court'])
            ->withCount([
                'participants as participants_count' => fn ($q) => $q->where('payment_status', '!=', 'cancelled'),
                'participants as confirmed_participants_count' => fn ($q) => $q->where('payment_status', 'confirmed'),
            ])
            ->get();

        $sessions = $sessions->sortBy('booking.start_time')->values();

        if ($user) {
            $viewerParticipants = OpenPlayParticipant::whereIn('open_play_session_id', $sessions->pluck('id'))
                ->where('user_id', $user->id)
                ->where('payment_status', '!=', 'cancelled')
                ->get()
                ->keyBy('open_play_session_id');

            $sessions->each(function (OpenPlaySession $session) use ($viewerParticipants): void {
                $session->setAttribute('viewer_participant', $viewerParticipants->get($session->id));
            });
        }

        return response()->json([
            'data' => OpenPlaySessionResource::collection($sessions),
        ]);
    }

    /**
     * Send a guest verification code for open play joining.
     */
    public function sendVerificationCode(
        SendGuestVerificationRequest $request,
        Hub $hub,
        OpenPlaySession $session
    ): JsonResponse {
        $this->assertSessionBelongsToHub($session, $hub);

        if (! $session->guests_can_join) {
            return response()->json(['message' => 'This session does not allow guest participants.'], 403);
        }

        if ($session->status !== 'open') {
            return response()->json(['message' => 'This session is not available for joining.'], 422);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->otpCacheKey($session, $request->email), $code, now()->addMinutes(10));

        $this->notifications->notifyGuestVerification($request->email, $code, $hub->name);

        return response()->json([
            'message' => 'Verification code sent. Check your email.',
        ]);
    }

    /**
     * Join an open play session.
     * Authenticated users join directly; guests require guests_can_join = true.
     */
    public function join(Hub $hub, OpenPlaySession $session, JoinOpenPlaySessionRequest $request): JsonResponse
    {
        $this->assertSessionBelongsToHub($session, $hub);

        if ($session->status !== 'open') {
            return response()->json(['message' => 'This session is not available for joining.'], 422);
        }

        $user = $request->user('sanctum');

        if ($user) {
            // Prevent duplicate join
            $existing = $session->participants()
                ->where('user_id', $user->id)
                ->whereNotIn('payment_status', ['cancelled'])
                ->exists();

            if ($existing) {
                return response()->json(['message' => 'You have already joined this session.'], 422);
            }
        } else {
            // Guest join
            if (! $session->guests_can_join) {
                return response()->json(['message' => 'This session does not allow guest participants.'], 403);
            }

            $storedOtp = Cache::get($this->otpCacheKey($session, $request->guest_email));

            if ($storedOtp === null || $storedOtp !== $request->otp) {
                return response()->json(['message' => 'Invalid or expired verification code.'], 422);
            }

            $existingGuest = $session->participants()
                ->where('guest_email', $request->guest_email)
                ->where('payment_status', '!=', 'cancelled')
                ->exists();

            if ($existingGuest) {
                return response()->json(['message' => 'This guest has already joined the session.'], 422);
            }
        }

        $isFree = (float) $session->price_per_player === 0.0;
        $startTime = $session->booking->start_time;

        $participant = $session->participants()->create([
            'user_id'              => $user?->id,
            'guest_name'           => $user ? null : $request->guest_name,
            'guest_phone'          => $user ? null : $request->guest_phone,
            'guest_email'          => $user ? null : $request->guest_email,
            'guest_tracking_token' => $user ? null : (string) Str::uuid(),
            'payment_method'       => $request->payment_method,
            'payment_status'       => $isFree ? 'confirmed' : 'pending_payment',
            'expires_at'           => $isFree ? null : $this->resolveExpiresAt($request->payment_method, $startTime),
            'joined_at'            => now(),
        ]);

        if (! $user) {
            Cache::forget($this->otpCacheKey($session, $request->guest_email));
        }

        if ($isFree) {
            $session->recalculateStatus();
        }

        return response()->json([
            'message' => $isFree ? "You've joined the session!" : 'Joined! Please complete your payment to confirm your spot.',
            'data'    => new OpenPlayParticipantResource($participant),
        ], 201);
    }

    /**
     * Leave an open play session. Auth required.
     */
    public function leave(Hub $hub, OpenPlaySession $session, Request $request): JsonResponse
    {
        $this->assertSessionBelongsToHub($session, $hub);
        $user = $request->user('sanctum');

        $participant = $session->participants()
            ->where('user_id', $user?->id)
            ->whereNotIn('payment_status', ['cancelled'])
            ->first();

        if (! $participant) {
            return response()->json(['message' => 'You are not a participant in this session.'], 404);
        }

        $participant->update([
            'payment_status' => 'cancelled',
            'cancelled_by'   => 'user',
        ]);

        $session->recalculateStatus();

        return response()->json(['message' => "You've left the session."]);
    }

    /**
     * Upload a payment receipt for a participant slot.
     * Authenticated user must own the participant row, or guest must supply matching token.
     */
    public function uploadReceipt(
        Hub $hub,
        OpenPlaySession $session,
        OpenPlayParticipant $participant,
        Request $request,
        ImageUploadService $imageUploadService
    ): JsonResponse {
        $this->assertSessionBelongsToHub($session, $hub);
        abort_if($participant->open_play_session_id !== $session->id, 404);

        // Auth: must be the owning user or the guest with a valid token
        $user = $request->user('sanctum');
        if ($user) {
            abort_if($participant->user_id !== $user->id, 403);
        } else {
            $token = $request->query('token') ?? $request->input('token');
            abort_if($participant->guest_tracking_token !== $token, 403);
        }

        if ($participant->payment_status !== 'pending_payment') {
            return response()->json(['message' => 'Receipt can only be uploaded when payment is pending.'], 422);
        }

        $request->validate(['receipt_image' => ['required', 'image', 'max:10240']]);

        $result = $imageUploadService->upload($request->file('receipt_image'), 'receipts');

        $participant->update([
            'receipt_image_url'   => $result['url'],
            'receipt_uploaded_at' => now(),
            'payment_status'      => 'payment_sent',
        ]);

        return response()->json([
            'message' => 'Receipt uploaded. The hub owner will review your payment.',
            'data'    => new OpenPlayParticipantResource($participant),
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────

    private function assertSessionBelongsToHub(OpenPlaySession $session, Hub $hub): void
    {
        $courtIds = $hub->courts()->pluck('id');
        abort_if(! $courtIds->contains($session->booking->court_id), 404);
    }

    private function resolveExpiresAt(string $paymentMethod, Carbon $startTime): Carbon
    {
        if ($paymentMethod === 'pay_on_site') {
            return $startTime->copy();
        }

        return now()->addHour()->min($startTime);
    }

    private function otpCacheKey(OpenPlaySession $session, string $email): string
    {
        return "open_play_guest_otp:{$session->id}:{$email}";
    }
}
