<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpenPlay\JoinOpenPlaySessionRequest;
use App\Http\Requests\OpenPlay\SendGuestVerificationRequest;
use App\Http\Resources\OpenPlayParticipantResource;
use App\Http\Resources\OpenPlaySessionResource;
use App\Models\Booking;
use App\Models\GuestBookingPenalty;
use App\Models\Hub;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlaySession;
use App\Models\User;
use App\Services\BookingNotificationService;
use App\Services\ImageUploadService;
use App\Services\OpenPlayNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OpenPlayController extends Controller
{
    public function __construct(
        private BookingNotificationService $notifications,
        private OpenPlayNotificationService $openPlayNotifications,
    ) {}

    /**
     * List upcoming open play sessions for a hub.
     * Public — no auth required.
     */
    public function index(Hub $hub, Request $request): JsonResponse
    {
        $courtIds = $hub->courts()->pluck('id');
        $user = $request->user('sanctum') ?? $request->user();

        $sessions = OpenPlaySession::whereIn('status', ['open', 'full'])
            ->whereHas('booking', fn ($q) => $q
                ->whereIn('court_id', $courtIds)
                ->where('status', 'confirmed')
                ->where('end_time', '>', now()->addHour())
            )
            ->with(['booking.court'])
            ->withCount([
                'participants as participants_count' => fn ($q) => $q
                    ->whereIn('payment_status', OpenPlaySession::reservedParticipantStatuses()),
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

        if ($response = $this->ensureGuestCanJoin($hub, $session, $request->email)) {
            return $response;
        }

        $existingGuest = $session->participants()
            ->where('guest_email', $request->email)
            ->where('payment_status', '!=', 'cancelled')
            ->exists();

        if ($existingGuest) {
            return response()->json(['message' => 'This email has already joined this session.'], 422);
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

        if ($session->booking->end_time->lte(now()->addHour())) {
            return response()->json(['message' => 'This session is no longer accepting new participants.'], 422);
        }

        if ($session->reservedParticipantCount() >= $session->max_players) {
            $session->recalculateStatus();

            return response()->json(['message' => 'This session is already full.'], 422);
        }

        $user = $request->user('sanctum') ?? $request->user();

        if ($user) {
            $response = $this->ensureUserCanJoin($user);
            if ($response) {
                return $response;
            }
        }

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

            if ($response = $this->ensureGuestCanJoin($hub, $session, $request->guest_email)) {
                return $response;
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

        $isFree   = (float) $session->price_per_player === 0.0;
        $endTime  = $session->booking->end_time;

        if ($session->reservedParticipantCount() >= $session->max_players) {
            $session->recalculateStatus();

            return response()->json(['message' => 'This session is already full.'], 422);
        }

        $participant = $session->participants()->create([
            'user_id'              => $user?->id,
            'guest_name'           => $user ? null : $request->guest_name,
            'guest_phone'          => $user ? null : $request->guest_phone,
            'guest_email'          => $user ? null : $request->guest_email,
            'guest_tracking_token' => $user ? null : (string) Str::uuid(),
            'payment_method'       => $request->payment_method,
            'payment_status'       => $isFree ? 'confirmed' : 'pending_payment',
            'expires_at'           => $isFree ? null : $this->resolveExpiresAt($endTime),
            'joined_at'            => now(),
        ]);

        if (! $user) {
            Cache::forget($this->otpCacheKey($session, $request->guest_email));
        }

        $session->recalculateStatus();

        $session->loadMissing('booking.court.hub');
        $participant->setRelation('openPlaySession', $session);
        $this->openPlayNotifications->notifyParticipantJoined($participant, $session);

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
        $user = $request->user('sanctum') ?? $request->user();

        $participant = $session->participants()
            ->where('user_id', $user?->id)
            ->whereNotIn('payment_status', ['cancelled'])
            ->first();

        if (! $participant) {
            return response()->json(['message' => 'You are not a participant in this session.'], 404);
        }

        if ($participant->payment_status === 'confirmed') {
            return response()->json(['message' => 'Confirmed participants cannot leave this session.'], 422);
        }

        $participant->update([
            'payment_status' => 'cancelled',
            'cancelled_by'   => 'user',
        ]);

        $session->recalculateStatus();

        $session->loadMissing('booking.court.hub.owner');
        $participant->setRelation('openPlaySession', $session);
        $participant->loadMissing('user');
        $this->openPlayNotifications->notifyParticipantCancelled($participant, $session, 'user');

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
        $user = $request->user('sanctum') ?? $request->user();
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

        $session->loadMissing('booking.court.hub.owner');
        $participant->setRelation('openPlaySession', $session);
        $this->openPlayNotifications->notifyReceiptUploaded($participant, $session);

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

    private function resolveExpiresAt(Carbon $endTime): Carbon
    {
        return $endTime->copy()->subHour();
    }

    private function otpCacheKey(OpenPlaySession $session, string $email): string
    {
        return "open_play_guest_otp:{$session->id}:{$email}";
    }

    private function ensureUserCanJoin(User $user): ?JsonResponse
    {
        if (! $user->isBookingBanned()) {
            return null;
        }

        return response()->json([
            'message' => 'Your account is temporarily restricted from making new bookings.',
            'banned_until' => $user->booking_banned_until?->toIso8601String(),
        ], 403);
    }

    private function ensureGuestCanJoin(Hub $hub, OpenPlaySession $session, string $email): ?JsonResponse
    {
        $penalty = GuestBookingPenalty::where('email', $email)->first();

        if ($penalty?->isBanned()) {
            return response()->json([
                'message' => 'This email is temporarily restricted from making new bookings.',
                'banned_until' => $penalty->banned_until?->toIso8601String(),
            ], 403);
        }

        $guestBookingLimit = $hub->settings?->guest_booking_limit ?? 1;
        $activeCommitments = $this->activeGuestCommitmentCount($hub, $email, $session->id);

        if ($activeCommitments >= $guestBookingLimit) {
            return response()->json([
                'message' => "You have reached the active guest limit ({$guestBookingLimit}) for bookings and open play joins at this hub.",
            ], 422);
        }

        return null;
    }

    private function activeGuestCommitmentCount(Hub $hub, string $email, ?string $excludingSessionId = null): int
    {
        $courtIds = $hub->courts()->pluck('id');

        $activeGuestBookings = Booking::whereIn('court_id', $courtIds)
            ->where('guest_email', $email)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('end_time', '>', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        $activeGuestOpenPlayJoins = OpenPlayParticipant::query()
            ->where('guest_email', $email)
            ->whereNotIn('payment_status', ['cancelled'])
            ->when($excludingSessionId, fn ($query) => $query->where('open_play_session_id', '!=', $excludingSessionId))
            ->whereHas('openPlaySession.booking', fn ($query) => $query
                ->whereIn('court_id', $courtIds)
                ->where('status', 'confirmed')
                ->where('end_time', '>', now())
            )
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        return $activeGuestBookings + $activeGuestOpenPlayJoins;
    }
}
