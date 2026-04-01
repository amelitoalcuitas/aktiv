<?php

use App\Events\BookingSlotUpdated;
use App\Events\NotificationBroadcast;
use App\Mail\OpenPlayJoinConfirmation;
use App\Mail\OpenPlayOwnerReceiptNotification;
use App\Mail\OpenPlayParticipantCancelled;
use App\Mail\OpenPlayParticipantConfirmed;
use App\Mail\OpenPlayParticipantRejected;
use App\Mail\OpenPlayPaymentPending;
use App\Mail\OpenPlaySessionCancelled;
use App\Mail\OpenPlaySessionStarted;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlaySession;
use App\Models\User;
use App\Services\OpenPlayNotificationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

// ── Helpers ───────────────────────────────────────────────────────

function makeNotifOwner(): User
{
    return User::factory()->create(['role' => 'owner']);
}

function makeNotifPlayer(): User
{
    return User::factory()->create(['role' => 'user']);
}

function makeNotifHub(User $owner): Hub
{
    $hub = Hub::factory()->create(['owner_id' => $owner->id, 'is_approved' => true, 'is_active' => true]);

    $hub->settings()->create([
        'payment_methods'      => ['pay_on_site', 'digital_bank'],
        'digital_bank_name'    => 'Aktiv Test Bank',
        'digital_bank_account' => '1234567890',
    ]);

    return $hub;
}

function makeNotifCourt(Hub $hub): Court
{
    return Court::factory()->create(['hub_id' => $hub->id]);
}

function makeNotifSession(Court $court, array $sessionOverrides = []): OpenPlaySession
{
    $start = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end   = $start->copy()->addHours(2);

    $booking = Booking::create([
        'court_id'       => $court->id,
        'start_time'     => $start,
        'end_time'       => $end,
        'session_type'   => 'open_play',
        'status'         => 'confirmed',
        'booking_source' => 'owner_added',
        'total_price'    => 0,
    ]);

    return OpenPlaySession::create(array_merge([
        'booking_id'       => $booking->id,
        'sport'            => 'badminton',
        'max_players'      => 8,
        'price_per_player' => 150.00,
        'guests_can_join'  => false,
        'status'           => 'open',
    ], $sessionOverrides));
}

function makeNotifParticipant(OpenPlaySession $session, User $user, array $overrides = []): OpenPlayParticipant
{
    return $session->participants()->create(array_merge([
        'user_id'        => $user->id,
        'payment_method' => 'digital_bank',
        'payment_status' => 'pending_payment',
        'joined_at'      => now(),
        'expires_at'     => now()->addHour(),
    ], $overrides));
}

function makeNotifGuestParticipant(OpenPlaySession $session, array $overrides = []): OpenPlayParticipant
{
    return $session->participants()->create(array_merge([
        'user_id'              => null,
        'guest_name'           => 'Test Guest',
        'guest_email'          => 'guest@example.com',
        'guest_tracking_token' => \Illuminate\Support\Str::uuid(),
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'pending_payment',
        'joined_at'            => now(),
        'expires_at'           => now()->addHour(),
    ], $overrides));
}

// ── notifyParticipantJoined ───────────────────────────────────────

it('sends join confirmation email to registered user on free session join', function () {
    Mail::fake();

    $owner  = makeNotifOwner();
    $hub    = makeNotifHub($owner);
    $court  = makeNotifCourt($hub);
    $player = makeNotifPlayer();

    $session = makeNotifSession($court, ['price_per_player' => 0]);
    $session->load('booking.court.hub');

    $participant = makeNotifParticipant($session, $player, ['payment_method' => 'pay_on_site', 'payment_status' => 'confirmed']);
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantJoined($participant, $session);

    Mail::assertQueued(OpenPlayJoinConfirmation::class, fn ($mail) => $mail->hasTo($player->email));
});

it('sends payment pending email to registered user on paid digital_bank join', function () {
    Mail::fake();

    $owner  = makeNotifOwner();
    $hub    = makeNotifHub($owner);
    $court  = makeNotifCourt($hub);
    $player = makeNotifPlayer();

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifParticipant($session, $player);
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantJoined($participant, $session);

    Mail::assertQueued(OpenPlayPaymentPending::class, fn ($mail) => $mail->hasTo($player->email));
});

it('sends join confirmation email to guest on pay_on_site join', function () {
    Mail::fake();

    $owner = makeNotifOwner();
    $hub   = makeNotifHub($owner);
    $court = makeNotifCourt($hub);

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifGuestParticipant($session, ['payment_method' => 'pay_on_site', 'payment_status' => 'pending_payment']);
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantJoined($participant, $session);

    Mail::assertQueued(OpenPlayJoinConfirmation::class, fn ($mail) => $mail->hasTo('guest@example.com'));
});

it('includes the guest tracking link in open play guest join confirmation emails', function () {
    Mail::fake();

    $owner = makeNotifOwner();
    $hub   = makeNotifHub($owner);
    $court = makeNotifCourt($hub);

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifGuestParticipant($session, ['payment_method' => 'pay_on_site', 'payment_status' => 'pending_payment']);
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantJoined($participant, $session);

    Mail::assertQueued(OpenPlayJoinConfirmation::class, function ($mail) use ($participant) {
        return $mail->hasTo('guest@example.com')
            && str_contains($mail->render(), "/open-play/track/{$participant->guest_tracking_token}");
    });
});

it('includes the guest tracking link in open play guest payment pending emails', function () {
    Mail::fake();

    $owner = makeNotifOwner();
    $hub   = makeNotifHub($owner);
    $court = makeNotifCourt($hub);

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifGuestParticipant($session);
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantJoined($participant, $session);

    Mail::assertQueued(OpenPlayPaymentPending::class, function ($mail) use ($participant) {
        return $mail->hasTo('guest@example.com')
            && str_contains($mail->render(), "/open-play/track/{$participant->guest_tracking_token}");
    });
});

// ── notifyReceiptUploaded ─────────────────────────────────────────

it('sends in-app notification and email to owner when receipt uploaded', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner  = makeNotifOwner();
    $hub    = makeNotifHub($owner);
    $court  = makeNotifCourt($hub);
    $player = makeNotifPlayer();

    $session = makeNotifSession($court);
    $session->load('booking.court.hub.owner');

    $participant = makeNotifParticipant($session, $player, ['payment_status' => 'payment_sent']);
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyReceiptUploaded($participant, $session);

    expect($owner->notifications()->count())->toBe(1)
        ->and($owner->notifications()->first()->data['activity_type'])->toBe('open_play_receipt_uploaded');

    Mail::assertQueued(OpenPlayOwnerReceiptNotification::class, fn ($mail) => $mail->hasTo($owner->email));
    Event::assertDispatched(NotificationBroadcast::class);
    Event::assertDispatched(BookingSlotUpdated::class);
});

// ── notifyParticipantConfirmed ────────────────────────────────────

it('sends in-app + broadcast notification to registered user when confirmed', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner  = makeNotifOwner();
    $hub    = makeNotifHub($owner);
    $court  = makeNotifCourt($hub);
    $player = makeNotifPlayer();

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifParticipant($session, $player, ['payment_status' => 'confirmed']);
    $participant->load('user');
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantConfirmed($participant, $session);

    expect($player->notifications()->count())->toBe(1)
        ->and($player->notifications()->first()->data['activity_type'])->toBe('open_play_participant_confirmed')
        ->and($player->notifications()->first()->data['item_id'])->toBe($participant->id)
        ->and($player->notifications()->first()->data['session_id'])->toBe($session->id);

    Event::assertDispatched(NotificationBroadcast::class);
    Event::assertDispatched(BookingSlotUpdated::class);
});

it('sends direct email to guest when confirmed', function () {
    Mail::fake();

    $owner = makeNotifOwner();
    $hub   = makeNotifHub($owner);
    $court = makeNotifCourt($hub);

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifGuestParticipant($session, ['payment_status' => 'confirmed']);
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantConfirmed($participant, $session);

    Mail::assertQueued(OpenPlayParticipantConfirmed::class, fn ($mail) => $mail->hasTo('guest@example.com'));
});

// ── notifyParticipantRejected ─────────────────────────────────────

it('sends in-app + broadcast notification to registered user when receipt rejected', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner  = makeNotifOwner();
    $hub    = makeNotifHub($owner);
    $court  = makeNotifCourt($hub);
    $player = makeNotifPlayer();

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifParticipant($session, $player, ['payment_status' => 'pending_payment', 'payment_note' => 'Blurry image']);
    $participant->load('user');
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantRejected($participant, $session);

    expect($player->notifications()->count())->toBe(1)
        ->and($player->notifications()->first()->data['activity_type'])->toBe('open_play_participant_rejected')
        ->and($player->notifications()->first()->data['item_id'])->toBe($participant->id)
        ->and($player->notifications()->first()->data['session_id'])->toBe($session->id);

    Event::assertDispatched(NotificationBroadcast::class);
});

it('sends direct email to guest when receipt rejected', function () {
    Mail::fake();

    $owner = makeNotifOwner();
    $hub   = makeNotifHub($owner);
    $court = makeNotifCourt($hub);

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifGuestParticipant($session, ['payment_status' => 'pending_payment', 'payment_note' => 'Blurry image']);
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantRejected($participant, $session);

    Mail::assertQueued(OpenPlayParticipantRejected::class, fn ($mail) => $mail->hasTo('guest@example.com'));
});

it('includes the guest tracking link in open play guest rejection emails', function () {
    Mail::fake();

    $owner = makeNotifOwner();
    $hub   = makeNotifHub($owner);
    $court = makeNotifCourt($hub);

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifGuestParticipant($session, ['payment_status' => 'pending_payment', 'payment_note' => 'Blurry image']);
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantRejected($participant, $session);

    Mail::assertQueued(OpenPlayParticipantRejected::class, function ($mail) use ($participant) {
        return $mail->hasTo('guest@example.com')
            && str_contains($mail->render(), "/open-play/track/{$participant->guest_tracking_token}");
    });
});

// ── notifyParticipantCancelled ────────────────────────────────────

it('sends in-app notification to registered user when owner cancels them', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner  = makeNotifOwner();
    $hub    = makeNotifHub($owner);
    $court  = makeNotifCourt($hub);
    $player = makeNotifPlayer();

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifParticipant($session, $player, ['payment_status' => 'cancelled', 'cancelled_by' => 'owner']);
    $participant->load('user');
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantCancelled($participant, $session, 'owner');

    expect($player->notifications()->count())->toBe(1)
        ->and($player->notifications()->first()->data['activity_type'])->toBe('open_play_participant_cancelled')
        ->and($player->notifications()->first()->data['item_id'])->toBe($participant->id)
        ->and($player->notifications()->first()->data['session_id'])->toBe($session->id);
});

it('sends direct email to guest when they are cancelled', function () {
    Mail::fake();

    $owner = makeNotifOwner();
    $hub   = makeNotifHub($owner);
    $court = makeNotifCourt($hub);

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifGuestParticipant($session, ['payment_status' => 'cancelled', 'cancelled_by' => 'system']);
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantCancelled($participant, $session, 'system');

    Mail::assertQueued(OpenPlayParticipantCancelled::class, fn ($mail) => $mail->hasTo('guest@example.com'));
});

it('includes the guest tracking link in open play guest cancellation emails', function () {
    Mail::fake();

    $owner = makeNotifOwner();
    $hub   = makeNotifHub($owner);
    $court = makeNotifCourt($hub);

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    $participant = makeNotifGuestParticipant($session, ['payment_status' => 'cancelled', 'cancelled_by' => 'system']);
    $participant->setRelation('openPlaySession', $session);

    app(OpenPlayNotificationService::class)->notifyParticipantCancelled($participant, $session, 'system');

    Mail::assertQueued(OpenPlayParticipantCancelled::class, function ($mail) use ($participant) {
        return $mail->hasTo('guest@example.com')
            && str_contains($mail->render(), "/open-play/track/{$participant->guest_tracking_token}");
    });
});

// ── notifySessionCancelled ────────────────────────────────────────

it('notifies all active participants when session is cancelled', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner   = makeNotifOwner();
    $hub     = makeNotifHub($owner);
    $court   = makeNotifCourt($hub);
    $player1 = makeNotifPlayer();
    $player2 = makeNotifPlayer();

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    makeNotifParticipant($session, $player1);
    makeNotifParticipant($session, $player2);
    makeNotifGuestParticipant($session);
    // Add an already-cancelled participant that should NOT be notified
    makeNotifParticipant($session, makeNotifPlayer(), ['payment_status' => 'cancelled', 'cancelled_by' => 'user']);

    app(OpenPlayNotificationService::class)->notifySessionCancelled($session);

    expect($player1->notifications()->count())->toBe(1)
        ->and($player1->notifications()->first()->data['item_id'])->toBe(
            $session->participants()->where('user_id', $player1->id)->firstOrFail()->id
        )
        ->and($player1->notifications()->first()->data['session_id'])->toBe($session->id);
    expect($player2->notifications()->count())->toBe(1)
        ->and($player2->notifications()->first()->data['item_id'])->toBe(
            $session->participants()->where('user_id', $player2->id)->firstOrFail()->id
        )
        ->and($player2->notifications()->first()->data['session_id'])->toBe($session->id);
    Mail::assertQueued(OpenPlaySessionCancelled::class, 1);
});

// ── notifySessionStarted ──────────────────────────────────────────

it('notifies all confirmed participants when session starts', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner   = makeNotifOwner();
    $hub     = makeNotifHub($owner);
    $court   = makeNotifCourt($hub);
    $player1 = makeNotifPlayer();
    $player2 = makeNotifPlayer();

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    makeNotifParticipant($session, $player1, ['payment_status' => 'confirmed', 'expires_at' => null]);
    makeNotifParticipant($session, $player2, ['payment_status' => 'confirmed', 'expires_at' => null]);
    makeNotifGuestParticipant($session, ['payment_status' => 'confirmed', 'expires_at' => null]);
    // pending_payment participant should NOT receive started notification
    makeNotifParticipant($session, makeNotifPlayer(), ['payment_status' => 'pending_payment']);

    app(OpenPlayNotificationService::class)->notifySessionStarted($session);

    expect($player1->notifications()->count())->toBe(1)
        ->and($player1->notifications()->first()->data['activity_type'])->toBe('open_play_session_started')
        ->and($player1->notifications()->first()->data['item_id'])->toBe(
            $session->participants()->where('user_id', $player1->id)->firstOrFail()->id
        )
        ->and($player1->notifications()->first()->data['session_id'])->toBe($session->id);
    expect($player2->notifications()->count())->toBe(1)
        ->and($player2->notifications()->first()->data['item_id'])->toBe(
            $session->participants()->where('user_id', $player2->id)->firstOrFail()->id
        )
        ->and($player2->notifications()->first()->data['session_id'])->toBe($session->id);
    Mail::assertQueued(OpenPlaySessionStarted::class, 1);
});

it('does not notify non-confirmed participants when session starts', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner  = makeNotifOwner();
    $hub    = makeNotifHub($owner);
    $court  = makeNotifCourt($hub);
    $player = makeNotifPlayer();

    $session = makeNotifSession($court);
    $session->load('booking.court.hub');

    makeNotifParticipant($session, $player, ['payment_status' => 'pending_payment']);

    app(OpenPlayNotificationService::class)->notifySessionStarted($session);

    expect($player->notifications()->count())->toBe(0);
    Mail::assertNothingQueued();
});
