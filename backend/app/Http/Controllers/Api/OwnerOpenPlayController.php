<?php

namespace App\Http\Controllers\Api;

use App\Events\BookingSlotUpdated;
use App\Http\Controllers\Controller;
use App\Services\OpenPlayNotificationService;
use App\Http\Requests\OpenPlay\StoreOpenPlaySessionRequest;
use App\Http\Requests\OpenPlay\UpdateOpenPlaySessionRequest;
use App\Http\Resources\OpenPlayParticipantResource;
use App\Http\Resources\OpenPlaySessionResource;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\HubEvent;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlaySession;
use App\Support\HubTimezone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerOpenPlayController extends Controller
{
    public function __construct(
        private OpenPlayNotificationService $openPlayNotifications,
    ) {}

    public function index(Hub $hub, Request $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);

        $courtIds = $hub->courts()->pluck('id');

        $query = OpenPlaySession::query()
            ->whereHas('booking', fn ($query) => $query->whereIn('court_id', $courtIds))
            ->with(['booking.court'])
            ->withCount([
                'participants as participants_count' => fn ($query) => $query
                    ->whereIn('payment_status', OpenPlaySession::reservedParticipantStatuses()),
                'participants as confirmed_participants_count' => fn ($query) => $query
                    ->where('payment_status', 'confirmed'),
            ]);

        $timezone = $hub->timezone_name;

        if ($request->filled('date_from')) {
            $query->whereHas('booking', fn ($bookingQuery) => $bookingQuery
                ->whereIn('court_id', $courtIds)
                ->where('end_time', '>=', HubTimezone::startOfDayUtc($request->date_from, $timezone)));
        }

        if ($request->filled('date_to')) {
            $query->whereHas('booking', fn ($bookingQuery) => $bookingQuery
                ->whereIn('court_id', $courtIds)
                ->where('start_time', '<=', HubTimezone::endOfDayUtc($request->date_to, $timezone)));
        }

        $sessions = $query
            ->get()
            ->sortByDesc(fn (OpenPlaySession $session) => $session->booking?->start_time?->getTimestamp() ?? 0)
            ->values();

        return response()->json([
            'data' => OpenPlaySessionResource::collection($sessions),
        ]);
    }

    public function store(Hub $hub, StoreOpenPlaySessionRequest $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);

        $court = Court::findOrFail($request->court_id);
        abort_if($court->hub_id !== $hub->id, 422);

        $startTime = Carbon::parse($request->start_time);
        $endTime   = Carbon::parse($request->end_time);

        $closureEvent = $this->findClosureEvent($hub, $court, $startTime, $endTime);
        if ($closureEvent) {
            return response()->json([
                'message' => "Court {$court->name} is unavailable because of the closure event \"{$closureEvent->title}\".",
            ], 422);
        }

        // Conflict detection — same logic as walk-in, excluding expired bookings
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

        $session = DB::transaction(function () use ($hub, $court, $request, $startTime, $endTime): OpenPlaySession {
            $booking = Booking::create([
                'court_id'       => $court->id,
                'created_by'     => $request->user()->id,
                'start_time'     => $startTime,
                'end_time'       => $endTime,
                'session_type'   => 'open_play',
                'status'         => 'confirmed',
                'booking_source' => 'owner_added',
                'total_price'    => 0,
                'expires_at'     => null,
            ]);

            return OpenPlaySession::create([
                'title'            => $request->title,
                'booking_id'       => $booking->id,
                'max_players'      => $request->max_players,
                'price_per_player' => $request->price_per_player,
                'notes'            => $request->description,
                'guests_can_join'  => $request->boolean('guests_can_join', false),
                'status'           => 'open',
            ]);
        });

        broadcast(new BookingSlotUpdated(
            hubId: $hub->id,
            courtId: $court->id,
            status: 'confirmed',
        ));

        $session = $this->loadSessionForResponse($session);

        return response()->json([
            'message' => 'Open play session created.',
            'data'    => new OpenPlaySessionResource($session),
        ], 201);
    }

    public function show(Hub $hub, OpenPlaySession $session, Request $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($session, $hub);

        return response()->json([
            'data' => new OpenPlaySessionResource($this->loadSessionForResponse($session)),
        ]);
    }

    public function update(Hub $hub, OpenPlaySession $session, UpdateOpenPlaySessionRequest $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($session, $hub);

        $court = Court::findOrFail($request->court_id);
        abort_if($court->hub_id !== $hub->id, 422);

        $booking = $session->booking;
        $booking->loadMissing('court.hub');
        $startTime = Carbon::parse($request->start_time);
        $endTime   = Carbon::parse($request->end_time);
        $originalCourtName = $booking->court->name;
        $originalStartTime = $booking->start_time->copy();
        $originalEndTime = $booking->end_time->copy();

        $closureEvent = $this->findClosureEvent($hub, $court, $startTime, $endTime);
        if ($closureEvent) {
            return response()->json([
                'message' => "Court {$court->name} is unavailable because of the closure event \"{$closureEvent->title}\".",
            ], 422);
        }

        $conflict = Booking::where('court_id', $court->id)
            ->where('id', '!=', $booking->id)
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

        $reservedParticipants = $session->participants()
            ->whereIn('payment_status', OpenPlaySession::reservedParticipantStatuses())
            ->count();

        if ($request->integer('max_players') < $reservedParticipants) {
            return response()->json([
                'message' => 'Max players cannot be lower than the number of active reserved participants.',
                'errors' => [
                    'max_players' => ['Max players cannot be lower than the number of active reserved participants.'],
                ],
            ], 422);
        }

        $originalCourtId = $booking->court_id;
        $shouldNotifyParticipants = $originalCourtId !== $court->id
            || ! $originalStartTime->equalTo($startTime)
            || ! $originalEndTime->equalTo($endTime);

        DB::transaction(function () use ($session, $booking, $court, $request, $startTime, $endTime): void {
            $booking->update([
                'court_id'   => $court->id,
                'start_time' => $startTime,
                'end_time'   => $endTime,
            ]);

            $session->update([
                'title'            => $request->title,
                'max_players'      => $request->max_players,
                'price_per_player' => $request->price_per_player,
                'notes'            => $request->description,
                'guests_can_join'  => $request->boolean('guests_can_join', false),
            ]);

            $session->recalculateStatus();
        });

        $session = $this->loadSessionForResponse($session->fresh()->load('booking.court.hub'));

        broadcast(new BookingSlotUpdated(
            hubId: $hub->id,
            courtId: $originalCourtId,
            status: $booking->status,
        ));

        if ($court->id !== $originalCourtId) {
            broadcast(new BookingSlotUpdated(
                hubId: $hub->id,
                courtId: $court->id,
                status: $booking->status,
            ));
        }

        if ($shouldNotifyParticipants) {
            $this->openPlayNotifications->notifySessionUpdated(
                $session,
                $originalCourtName,
                $originalStartTime,
                $originalEndTime,
            );
        }

        return response()->json([
            'message' => 'Open play session updated.',
            'data'    => new OpenPlaySessionResource($session),
        ]);
    }

    public function destroy(Hub $hub, OpenPlaySession $session, Request $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($session, $hub);

        if ($session->status === 'cancelled') {
            return response()->json(['message' => 'Session is already cancelled.'], 422);
        }

        $courtId = $session->booking->court_id;

        $session->loadMissing([
            'booking.court.hub',
            'participants' => fn ($query) => $query
                ->whereNotIn('payment_status', ['cancelled'])
                ->with('user'),
        ]);

        DB::transaction(function () use ($session): void {
            // Cancel all active participants
            $session->participants()
                ->whereNotIn('payment_status', ['cancelled'])
                ->update(['payment_status' => 'cancelled', 'cancelled_by' => 'system']);

            // Cancel the underlying booking
            $session->booking->update(['status' => 'cancelled', 'cancelled_by' => 'owner']);

            // Cancel the session
            $session->update(['status' => 'cancelled']);
        });

        $this->openPlayNotifications->notifySessionCancelled($session);

        broadcast(new BookingSlotUpdated(
            hubId: $hub->id,
            courtId: $courtId,
            status: 'cancelled',
        ));

        return response()->json(['message' => 'Open play session cancelled.']);
    }

    public function participants(Hub $hub, OpenPlaySession $session, Request $request): JsonResponse
    {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($session, $hub);

        $session->load([
            'participants' => fn ($query) => $query
                ->where('payment_status', '!=', 'cancelled')
                ->with('user'),
        ]);

        return response()->json([
            'data' => OpenPlayParticipantResource::collection($session->participants),
        ]);
    }

    public function confirmParticipant(
        Hub $hub,
        OpenPlaySession $session,
        OpenPlayParticipant $participant,
        Request $request
    ): JsonResponse {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($session, $hub);
        abort_if($participant->open_play_session_id !== $session->id, 404);

        if (! in_array($participant->payment_status, ['pending_payment', 'payment_sent'])) {
            return response()->json(['message' => 'This participant cannot be confirmed.'], 422);
        }

        if (! in_array($participant->payment_status, OpenPlaySession::reservedParticipantStatuses(), true)) {
            return response()->json(['message' => 'This participant no longer has a reserved seat.'], 422);
        }

        $participant->update([
            'payment_status'       => 'confirmed',
            'payment_confirmed_by' => $request->user()->id,
            'payment_confirmed_at' => now(),
        ]);

        $session->recalculateStatus();

        $session->loadMissing('booking.court.hub');
        $participant->setRelation('openPlaySession', $session);
        $participant->loadMissing('user');
        $this->openPlayNotifications->notifyParticipantConfirmed($participant, $session);

        return response()->json([
            'message' => 'Participant confirmed.',
            'data'    => new OpenPlayParticipantResource($participant->load('user')),
        ]);
    }

    public function rejectParticipant(
        Hub $hub,
        OpenPlaySession $session,
        OpenPlayParticipant $participant,
        Request $request
    ): JsonResponse {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($session, $hub);
        abort_if($participant->open_play_session_id !== $session->id, 404);

        $request->validate(['payment_note' => ['required', 'string', 'max:500']]);

        if ($participant->payment_status !== 'payment_sent') {
            return response()->json(['message' => 'Only receipts awaiting review can be rejected.'], 422);
        }

        $participant->update([
            'payment_status'      => 'pending_payment',
            'payment_note'        => $request->payment_note,
            'receipt_image_url'   => null,
            'receipt_uploaded_at' => null,
            'expires_at'          => $this->resolveExpiresAt($session->booking->end_time),
        ]);

        $session->loadMissing('booking.court.hub');
        $participant->setRelation('openPlaySession', $session);
        $participant->loadMissing('user');
        $this->openPlayNotifications->notifyParticipantRejected($participant, $session);

        return response()->json([
            'message' => 'Receipt rejected. Participant can re-upload.',
            'data'    => new OpenPlayParticipantResource($participant->load('user')),
        ]);
    }

    public function cancelParticipant(
        Hub $hub,
        OpenPlaySession $session,
        OpenPlayParticipant $participant,
        Request $request
    ): JsonResponse {
        abort_if($hub->owner_id !== $request->user()->id, 403);
        $this->assertBelongsToHub($session, $hub);
        abort_if($participant->open_play_session_id !== $session->id, 404);

        if ($participant->payment_status === 'cancelled') {
            return response()->json(['message' => 'Participant is already cancelled.'], 422);
        }

        $participant->update([
            'payment_status' => 'cancelled',
            'cancelled_by'   => 'owner',
        ]);

        $session->recalculateStatus();

        $session->loadMissing('booking.court.hub');
        $participant->setRelation('openPlaySession', $session);
        $participant->loadMissing('user');
        $this->openPlayNotifications->notifyParticipantCancelled($participant, $session, 'owner');

        return response()->json([
            'message' => 'Participant cancelled.',
            'data'    => new OpenPlayParticipantResource($participant->load('user')),
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────

    private function assertBelongsToHub(OpenPlaySession $session, Hub $hub): void
    {
        $courtIds = $hub->courts()->pluck('id');
        abort_if(! $courtIds->contains($session->booking->court_id), 404);
    }

    private function loadSessionForResponse(OpenPlaySession $session): OpenPlaySession
    {
        return $session->load(['booking.court.hub'])
            ->loadCount([
                'participants as participants_count' => fn ($query) => $query
                    ->whereIn('payment_status', OpenPlaySession::reservedParticipantStatuses()),
                'participants as confirmed_participants_count' => fn ($query) => $query->where('payment_status', 'confirmed'),
            ]);
    }

    private function findClosureEvent(Hub $hub, Court $court, Carbon $startTime, Carbon $endTime): ?HubEvent
    {
        return HubEvent::where('hub_id', $hub->id)
            ->where('event_type', 'closure')
            ->where('is_active', true)
            ->get()
            ->first(fn (HubEvent $event) => $event->overlapsWindow($startTime, $endTime)
                && $event->appliesToCourt($court->id));
    }

    private function resolveExpiresAt(Carbon $endTime): Carbon
    {
        return $endTime->copy()->subHour();
    }
}
