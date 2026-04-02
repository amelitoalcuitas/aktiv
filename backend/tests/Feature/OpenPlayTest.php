<?php

use App\Console\Commands\CancelExpiredBookings;
use App\Events\BookingSlotUpdated;
use App\Events\NotificationBroadcast;
use App\Models\Booking;
use App\Models\Court;
use App\Models\GuestBookingPenalty;
use App\Models\Hub;
use App\Models\HubEvent;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlaySession;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

// ── Helpers ────────────────────────────────────────────────────

function makeOwner(): User
{
    return User::factory()->create(['role' => 'owner']);
}

function makePlayer(): User
{
    return User::factory()->create(['role' => 'user']);
}

function makeOwnerHub(User $owner): Hub
{
    $hub = Hub::factory()->create(['owner_id' => $owner->id, 'is_approved' => true, 'is_active' => true]);

    $hub->settings()->create([
        'payment_methods'      => ['pay_on_site', 'digital_bank'],
        'digital_bank_name'    => 'Aktiv Test Bank',
        'digital_bank_account' => '1234567890',
    ]);

    return $hub;
}

function makeHubCourt(Hub $hub): Court
{
    return Court::factory()->create(['hub_id' => $hub->id]);
}

function makeOpenPlaySession(Court $court, array $overrides = []): OpenPlaySession
{
    $start = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end   = $start->copy()->addHours(2);

    $booking = Booking::create(array_merge([
        'court_id'       => $court->id,
        'sport'          => 'badminton',
        'start_time'     => $start,
        'end_time'       => $end,
        'session_type'   => 'open_play',
        'status'         => 'confirmed',
        'booking_source' => 'owner_added',
        'total_price'    => 0,
    ], $overrides['booking'] ?? []));

    return OpenPlaySession::create(array_merge([
        'booking_id'       => $booking->id,
        'title'            => 'Open Play',
        'sport'            => 'badminton',
        'max_players'      => 8,
        'price_per_player' => 150.00,
        'guests_can_join'  => false,
        'status'           => 'open',
    ], $overrides['session'] ?? []));
}

// ── Owner: Create session ──────────────────────────────────────

it('owner can create an open play session', function () {
    $owner = makeOwner();
    $hub   = makeOwnerHub($owner);
    $court = makeHubCourt($hub);

    $start = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->toIso8601String();
    $end   = now()->addDay()->setHour(12)->setMinute(0)->setSecond(0)->toIso8601String();

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/open-play", [
            'title'            => 'Friday Night Doubles',
            'court_id'         => $court->id,
            'start_time'       => $start,
            'end_time'         => $end,
            'max_players'      => 8,
            'price_per_player' => 150,
            'guests_can_join'  => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Friday Night Doubles')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonMissingPath('data.sport');

    expect(OpenPlaySession::count())->toBe(1);
    expect(Booking::where('session_type', 'open_play')->count())->toBe(1);
});

it('owner cannot create a session on another hub\'s court', function () {
    $owner      = makeOwner();
    $hub        = makeOwnerHub($owner);
    $otherHub   = Hub::factory()->create(['is_approved' => true, 'is_active' => true]);
    $otherCourt = makeHubCourt($otherHub);

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/open-play", [
            'title'            => 'Blocked Session',
            'court_id'         => $otherCourt->id,
            'start_time'       => now()->addDay()->setHour(10)->toIso8601String(),
            'end_time'         => now()->addDay()->setHour(12)->toIso8601String(),
            'max_players'      => 8,
            'price_per_player' => 150,
        ])
        ->assertUnprocessable();
});

it('owner can fetch hub open play sessions for dashboard management', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'pay_on_site',
        'payment_status' => 'confirmed',
        'joined_at' => now(),
    ]);
    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'pay_on_site',
        'payment_status' => 'cancelled',
        'joined_at' => now(),
    ]);

    $this->actingAs($owner)
        ->getJson("/api/dashboard/hubs/{$hub->id}/open-play")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $session->id)
        ->assertJsonPath('data.0.title', 'Open Play')
        ->assertJsonPath('data.0.booking.court.id', $court->id)
        ->assertJsonPath('data.0.participants_count', 1)
        ->assertJsonPath('data.0.confirmed_participants_count', 1)
        ->assertJsonMissingPath('data.0.sport');
});

it('owner cannot fetch another owner\'s open play sessions', function () {
    $owner = makeOwner();
    $otherOwner = makeOwner();
    $hub = makeOwnerHub($otherOwner);
    $court = makeHubCourt($hub);

    makeOpenPlaySession($court);

    $this->actingAs($owner)
        ->getJson("/api/dashboard/hubs/{$hub->id}/open-play")
        ->assertForbidden();
});

it('owner can fetch a single open play session', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'pay_on_site',
        'payment_status' => 'confirmed',
        'joined_at' => now(),
    ]);
    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'pay_on_site',
        'payment_status' => 'cancelled',
        'joined_at' => now(),
    ]);

    $this->actingAs($owner)
        ->getJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $session->id)
        ->assertJsonPath('data.title', 'Open Play')
        ->assertJsonPath('data.booking_id', $session->booking_id)
        ->assertJsonPath('data.booking.court.id', $court->id)
        ->assertJsonPath('data.participants_count', 1)
        ->assertJsonPath('data.confirmed_participants_count', 1)
        ->assertJsonMissingPath('data.sport');
});

it('owner can update open play session metadata', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);

    $start = now()->addDays(2)->setHour(14)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHours(2);

    $this->actingAs($owner)
        ->putJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}", [
            'title' => 'Beginner Badminton Night',
            'court_id' => $court->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'max_players' => 10,
            'price_per_player' => 200,
            'description' => 'Bring water.',
            'guests_can_join' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Beginner Badminton Night')
        ->assertJsonPath('data.description', 'Bring water.')
        ->assertJsonPath('data.max_players', 10)
        ->assertJsonPath('data.price_per_player', '200.00')
        ->assertJsonPath('data.notes', 'Bring water.')
        ->assertJsonPath('data.guests_can_join', true)
        ->assertJsonPath('data.booking.start_time', $start->toIso8601String())
        ->assertJsonPath('data.booking.end_time', $end->toIso8601String())
        ->assertJsonMissingPath('data.sport');

    expect($session->fresh()->max_players)->toBe(10);
    expect($session->fresh()->price_per_player)->toBe('200.00');
    expect($session->fresh()->title)->toBe('Beginner Badminton Night');
});

it('owner can move an open play session to another available slot and court', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $targetCourt = Court::factory()->create([
        'hub_id' => $hub->id,
        'name' => 'Court Z',
    ]);
    $session = makeOpenPlaySession($court);

    $start = now()->addDays(3)->setHour(16)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHours(2);

    $this->actingAs($owner)
        ->putJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}", [
            'title' => 'Moved Session',
            'court_id' => $targetCourt->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'max_players' => 8,
            'price_per_player' => 150,
            'description' => null,
            'guests_can_join' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.booking.court.id', $targetCourt->id)
        ->assertJsonPath('data.booking.start_time', $start->toIso8601String());

    expect($session->booking->fresh()->court_id)->toBe($targetCourt->id);
});

it('owner cannot update a session to a conflicting slot', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);

    $start = now()->addDays(2)->setHour(18)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHours(2);

    Booking::create([
        'court_id' => $court->id,
        'sport' => 'badminton',
        'start_time' => $start,
        'end_time' => $end,
        'session_type' => 'private',
        'status' => 'confirmed',
        'booking_source' => 'owner_added',
        'total_price' => 0,
    ]);

    $this->actingAs($owner)
        ->putJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}", [
            'title' => 'Conflict Session',
            'court_id' => $court->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'max_players' => 8,
            'price_per_player' => 150,
            'description' => null,
            'guests_can_join' => false,
        ])
        ->assertStatus(409);
});

it('owner cannot update a session into a closure event', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);

    $date = now('Asia/Manila')->addDays(4);

    HubEvent::factory()->closure()->create([
        'hub_id' => $hub->id,
        'title' => 'Maintenance',
        'date_from' => $date->toDateString(),
        'date_to' => $date->toDateString(),
        'time_from' => '17:00:00',
        'time_to' => '20:00:00',
        'affected_courts' => [$court->id],
        'is_active' => true,
    ]);

    $start = $date->copy()->setHour(18)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHour();

    $this->actingAs($owner)
        ->putJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}", [
            'title' => 'Maintenance Conflict',
            'court_id' => $court->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'max_players' => 8,
            'price_per_player' => 150,
            'description' => null,
            'guests_can_join' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This court is unavailable: Maintenance');
});

it('owner cannot reduce max players below active reserved participants', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'pay_on_site',
        'payment_status' => 'pending_payment',
        'joined_at' => now(),
    ]);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'digital_bank',
        'payment_status' => 'payment_sent',
        'joined_at' => now(),
    ]);

    $this->actingAs($owner)
        ->putJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}", [
            'title' => 'Too Small Session',
            'court_id' => $court->id,
            'start_time' => $session->booking->start_time->toIso8601String(),
            'end_time' => $session->booking->end_time->toIso8601String(),
            'max_players' => 1,
            'price_per_player' => 150,
            'description' => null,
            'guests_can_join' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['max_players']);
});

it('owner cannot create a session on a conflicting slot', function () {
    $owner = makeOwner();
    $hub   = makeOwnerHub($owner);
    $court = makeHubCourt($hub);

    $start = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end   = $start->copy()->addHours(2);

    // Existing confirmed booking on same slot
    Booking::create([
        'court_id'       => $court->id,
        'sport'          => 'badminton',
        'start_time'     => $start,
        'end_time'       => $end,
        'session_type'   => 'private',
        'status'         => 'confirmed',
        'booking_source' => 'owner_added',
        'total_price'    => 0,
    ]);

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/open-play", [
            'title'            => 'Conflicting New Session',
            'court_id'         => $court->id,
            'start_time'       => $start->toIso8601String(),
            'end_time'         => $end->toIso8601String(),
            'max_players'      => 8,
            'price_per_player' => 150,
        ])
        ->assertStatus(409);
});

it('non-owner cannot create a session', function () {
    $owner  = makeOwner();
    $hub    = makeOwnerHub($owner);
    $court  = makeHubCourt($hub);
    $player = makePlayer();

    $this->actingAs($player)
        ->postJson("/api/dashboard/hubs/{$hub->id}/open-play", [
            'title'            => 'Unauthorized Session',
            'court_id'         => $court->id,
            'start_time'       => now()->addDay()->setHour(10)->toIso8601String(),
            'end_time'         => now()->addDay()->setHour(12)->toIso8601String(),
            'max_players'      => 8,
            'price_per_player' => 150,
        ])
        ->assertForbidden();
});

// ── Owner: Cancel session ──────────────────────────────────────

it('owner can cancel an open play session', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);

    $this->actingAs($owner)
        ->deleteJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}")
        ->assertOk();

    expect($session->fresh()->status)->toBe('cancelled');
    expect($session->booking->fresh()->status)->toBe('cancelled');
});

it('cancelling a session cancels all its participants', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method'       => 'pay_on_site',
        'payment_status'       => 'confirmed',
        'joined_at'            => now(),
    ]);

    $this->actingAs($owner)
        ->deleteJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}")
        ->assertOk();

    expect(OpenPlayParticipant::where('open_play_session_id', $session->id)
        ->where('payment_status', 'cancelled')
        ->count()
    )->toBe(1);
});

// ── Public: List sessions ──────────────────────────────────────

it('lists upcoming open play sessions for a hub', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);

    makeOpenPlaySession($court);
    makeOpenPlaySession($court);

    $this->getJson("/api/hubs/{$hub->id}/open-play")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('owner participant list excludes cancelled participants', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'pay_on_site',
        'payment_status' => 'confirmed',
        'joined_at' => now(),
    ]);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'pay_on_site',
        'payment_status' => 'cancelled',
        'joined_at' => now(),
    ]);

    $this->actingAs($owner)
        ->getJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}/participants")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.payment_status', 'confirmed');
});

it('includes viewer participant metadata for the authenticated user', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);
    $player = makePlayer();

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'user_id' => $player->id,
        'payment_method' => 'digital_bank',
        'payment_status' => 'pending_payment',
        'guest_tracking_token' => null,
        'expires_at' => now()->addMinutes(30),
        'joined_at' => now(),
    ]);

    $this->actingAs($player)
        ->getJson("/api/hubs/{$hub->id}/open-play")
        ->assertOk()
        ->assertJsonPath('data.0.viewer_participant.user_id', $player->id)
        ->assertJsonPath('data.0.viewer_participant.payment_status', 'pending_payment');
});

it('lists active reserved participants separately from confirmed participants', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'pay_on_site',
        'payment_status' => 'pending_payment',
        'joined_at' => now(),
    ]);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'digital_bank',
        'payment_status' => 'payment_sent',
        'joined_at' => now(),
    ]);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'pay_on_site',
        'payment_status' => 'confirmed',
        'joined_at' => now(),
    ]);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'payment_method' => 'pay_on_site',
        'payment_status' => 'cancelled',
        'joined_at' => now(),
    ]);

    $this->getJson("/api/hubs/{$hub->id}/open-play")
        ->assertOk()
        ->assertJsonPath('data.0.participants_count', 3)
        ->assertJsonPath('data.0.confirmed_participants_count', 1)
        ->assertJsonPath('data.0.status', 'open');
});

it('does not list cancelled sessions', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);

    makeOpenPlaySession($court);
    makeOpenPlaySession($court, ['session' => ['status' => 'cancelled']]);

    $this->getJson("/api/hubs/{$hub->id}/open-play")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('lists in-progress open play sessions that have more than 1 hour remaining', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);

    $session = makeOpenPlaySession($court, [
        'booking' => [
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
        ],
    ]);

    $this->getJson("/api/hubs/{$hub->id}/open-play")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $session->id);
});

it('does not list in-progress sessions with less than 1 hour remaining', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);

    makeOpenPlaySession($court, [
        'booking' => [
            'start_time' => now()->subMinutes(30),
            'end_time' => now()->addMinutes(30),
        ],
    ]);

    $this->getJson("/api/hubs/{$hub->id}/open-play")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('does not list open play sessions once they have ended', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);

    makeOpenPlaySession($court, [
        'booking' => [
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
        ],
    ]);

    $this->getJson("/api/hubs/{$hub->id}/open-play")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ── Join ────────────────────────────────────────────────────────

it('authenticated user can join an open play session', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);
    $player  = makePlayer();

    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertCreated()
        ->assertJsonPath('data.payment_status', 'pending_payment');

    expect(OpenPlayParticipant::where('open_play_session_id', $session->id)->count())->toBe(1);
});

it('booking-banned authenticated user cannot join an open play session', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);
    $player = makePlayer();
    $player->booking_banned_until = now()->addDays(2);
    $player->save();

    $this->actingAs($player, 'sanctum')
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Your account is temporarily restricted from making new bookings.')
        ->assertJsonPath('banned_until', $player->fresh()->booking_banned_until->toIso8601String());

    expect(OpenPlayParticipant::where('open_play_session_id', $session->id)->count())->toBe(0);
});

it('authenticated user can join an in-progress open play session that has more than 1 hour remaining', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, [
        'booking' => [
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
        ],
    ]);
    $player = makePlayer();

    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertCreated()
        ->assertJsonPath('data.payment_status', 'pending_payment');
});

it('user cannot join an in-progress session with less than 1 hour remaining', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, [
        'booking' => [
            'start_time' => now()->subMinutes(30),
            'end_time' => now()->addMinutes(30),
        ],
    ]);
    $player = makePlayer();

    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This session is no longer accepting new participants.');
});

it('authenticated user can join without guest fields', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);
    $player = makePlayer();

    $this->actingAs($player, 'sanctum')
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertCreated()
        ->assertJsonMissingValidationErrors([
            'guest_name',
            'guest_phone',
            'guest_email',
            'otp',
        ]);

    expect(
        OpenPlayParticipant::where('open_play_session_id', $session->id)
            ->where('user_id', $player->id)
            ->exists()
    )->toBeTrue();
});

it('joining a free session confirms the participant immediately', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['price_per_player' => 0]]);
    $player  = makePlayer();

    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertCreated()
        ->assertJsonPath('data.payment_status', 'confirmed');
});

it('user cannot join the same session twice', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);
    $player  = makePlayer();

    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertCreated();

    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertUnprocessable();
});

it('user cannot join a full session', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['max_players' => 1, 'price_per_player' => 0, 'status' => 'full']]);
    $player  = makePlayer();

    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertUnprocessable();
});

it('session becomes full when active reserved participant count reaches max_players', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['max_players' => 1]]);
    $player  = makePlayer();

    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertCreated()
        ->assertJsonPath('data.payment_status', 'pending_payment');

    expect($session->fresh()->status)->toBe('full');
});

it('user cannot join when all seats are already reserved by active participants', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['max_players' => 1]]);
    $firstPlayer = makePlayer();
    $secondPlayer = makePlayer();

    $this->actingAs($firstPlayer)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'digital_bank',
        ])
        ->assertCreated()
        ->assertJsonPath('data.payment_status', 'pending_payment');

    $this->actingAs($secondPlayer)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This session is not available for joining.');
});

it('guest cannot join when guests_can_join is false', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['guests_can_join' => false]]);

    $this->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
        'payment_method' => 'pay_on_site',
        'guest_name'     => 'Juan',
        'guest_phone'    => '09171234567',
        'guest_email'    => 'juan@example.com',
        'otp'            => '123456',
    ])
    ->assertForbidden();
});

it('guest join still requires guest fields and otp', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['guests_can_join' => true]]);

    $this->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
        'payment_method' => 'pay_on_site',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'guest_name',
            'guest_phone',
            'guest_email',
            'otp',
        ]);
});

it('guest can request a verification code for an open play session', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['guests_can_join' => true]]);

    $this->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/guest-verify", [
        'email' => 'guest@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Verification code sent. Check your email.');

    expect(Cache::get("open_play_guest_otp:{$session->id}:guest@example.com"))->not->toBeNull();
});

it('banned guest cannot request an open play verification code', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['guests_can_join' => true]]);

    $penalty = GuestBookingPenalty::create([
        'email' => 'guest@example.com',
        'strikes' => 0,
        'banned_until' => now()->addDays(2),
    ]);

    $this->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/guest-verify", [
        'email' => 'guest@example.com',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'This email is temporarily restricted from making new bookings.')
        ->assertJsonPath('banned_until', $penalty->banned_until->toIso8601String());

    expect(Cache::get("open_play_guest_otp:{$session->id}:guest@example.com"))->toBeNull();
});

it('guest verification is blocked when the shared guest hub limit is already reached by a booking', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['guests_can_join' => true]]);

    Booking::create([
        'court_id' => $court->id,
        'guest_name' => 'Existing Guest',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '09171234567',
        'sport' => 'badminton',
        'start_time' => now()->addDay()->setHour(14),
        'end_time' => now()->addDay()->setHour(16),
        'session_type' => 'private',
        'status' => 'pending_payment',
        'booking_source' => 'self_booked',
        'payment_method' => 'pay_on_site',
        'total_price' => 500,
        'expires_at' => now()->addHour(),
    ]);

    $this->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/guest-verify", [
        'email' => 'guest@example.com',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You have reached the active guest limit (1) for bookings and open play joins at this hub.');
});

it('guest can join with a valid verification code', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['guests_can_join' => true]]);

    Cache::put("open_play_guest_otp:{$session->id}:guest@example.com", '123456', now()->addMinutes(10));

    $this->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
        'payment_method' => 'pay_on_site',
        'guest_name' => 'Guest Player',
        'guest_phone' => '09171234567',
        'guest_email' => 'guest@example.com',
        'otp' => '123456',
    ])
        ->assertCreated()
        ->assertJsonPath('data.payment_status', 'pending_payment')
        ->assertJsonPath('data.guest_email', 'guest@example.com');

    expect(OpenPlayParticipant::where('open_play_session_id', $session->id)->count())->toBe(1);
    expect(Cache::get("open_play_guest_otp:{$session->id}:guest@example.com"))->toBeNull();
});

it('banned guest cannot join an open play session even with a valid verification code', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['guests_can_join' => true]]);

    Cache::put("open_play_guest_otp:{$session->id}:guest@example.com", '123456', now()->addMinutes(10));

    $penalty = GuestBookingPenalty::create([
        'email' => 'guest@example.com',
        'strikes' => 0,
        'banned_until' => now()->addDays(2),
    ]);

    $this->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
        'payment_method' => 'pay_on_site',
        'guest_name' => 'Guest Player',
        'guest_phone' => '09171234567',
        'guest_email' => 'guest@example.com',
        'otp' => '123456',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'This email is temporarily restricted from making new bookings.')
        ->assertJsonPath('banned_until', $penalty->banned_until->toIso8601String());

    expect(OpenPlayParticipant::where('open_play_session_id', $session->id)->count())->toBe(0);
});

it('guest join is blocked when the shared guest hub limit is already reached by another open play join', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $existingSession = makeOpenPlaySession($court, ['session' => ['guests_can_join' => true]]);
    $targetSession = makeOpenPlaySession($court, [
        'booking' => [
            'start_time' => now()->addDays(2)->setHour(10)->setMinute(0)->setSecond(0),
            'end_time' => now()->addDays(2)->setHour(12)->setMinute(0)->setSecond(0),
        ],
        'session' => ['guests_can_join' => true],
    ]);

    OpenPlayParticipant::create([
        'open_play_session_id' => $existingSession->id,
        'guest_name' => 'Guest Player',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '09171234567',
        'guest_tracking_token' => null,
        'payment_method' => 'pay_on_site',
        'payment_status' => 'pending_payment',
        'expires_at' => now()->addHour(),
        'joined_at' => now(),
    ]);

    Cache::put("open_play_guest_otp:{$targetSession->id}:guest@example.com", '123456', now()->addMinutes(10));

    $this->postJson("/api/hubs/{$hub->id}/open-play/{$targetSession->id}/join", [
        'payment_method' => 'pay_on_site',
        'guest_name' => 'Guest Player',
        'guest_phone' => '09171234567',
        'guest_email' => 'guest@example.com',
        'otp' => '123456',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You have reached the active guest limit (1) for bookings and open play joins at this hub.');
});

it('expired guest open play joins do not count toward the shared guest hub limit', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $existingSession = makeOpenPlaySession($court, ['session' => ['guests_can_join' => true]]);
    $targetSession = makeOpenPlaySession($court, [
        'booking' => [
            'start_time' => now()->addDays(2)->setHour(10)->setMinute(0)->setSecond(0),
            'end_time' => now()->addDays(2)->setHour(12)->setMinute(0)->setSecond(0),
        ],
        'session' => ['guests_can_join' => true],
    ]);

    OpenPlayParticipant::create([
        'open_play_session_id' => $existingSession->id,
        'guest_name' => 'Guest Player',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '09171234567',
        'payment_method' => 'digital_bank',
        'payment_status' => 'pending_payment',
        'expires_at' => now()->subMinute(),
        'joined_at' => now()->subHours(2),
    ]);

    $this->postJson("/api/hubs/{$hub->id}/open-play/{$targetSession->id}/guest-verify", [
        'email' => 'guest@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Verification code sent. Check your email.');
});

it('guest cannot join with an invalid verification code', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['guests_can_join' => true]]);

    Cache::put("open_play_guest_otp:{$session->id}:guest@example.com", '123456', now()->addMinutes(10));

    $this->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
        'payment_method' => 'pay_on_site',
        'guest_name' => 'Guest Player',
        'guest_phone' => '09171234567',
        'guest_email' => 'guest@example.com',
        'otp' => '999999',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid or expired verification code.');
});

// ── Leave ──────────────────────────────────────────────────────

it('user can leave a session', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['max_players' => 1]]);
    $player  = makePlayer();

    // Join first
    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", ['payment_method' => 'pay_on_site'])
        ->assertCreated();

    expect($session->fresh()->status)->toBe('full');

    // Leave
    $this->actingAs($player)
        ->deleteJson("/api/hubs/{$hub->id}/open-play/{$session->id}/leave")
        ->assertOk();

    expect(
        OpenPlayParticipant::where('open_play_session_id', $session->id)
            ->where('payment_status', 'cancelled')
            ->count()
    )->toBe(1);

    // Session should revert to open
    expect($session->fresh()->status)->toBe('open');
});

it('confirmed participant cannot leave a session', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['price_per_player' => 0]]);
    $player = makePlayer();

    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", ['payment_method' => 'pay_on_site'])
        ->assertCreated()
        ->assertJsonPath('data.payment_status', 'confirmed');

    $this->actingAs($player)
        ->deleteJson("/api/hubs/{$hub->id}/open-play/{$session->id}/leave")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Confirmed participants cannot leave this session.');

    expect(
        OpenPlayParticipant::where('open_play_session_id', $session->id)
            ->where('user_id', $player->id)
            ->where('payment_status', 'confirmed')
            ->exists()
    )->toBeTrue();
});

it('payment sent participant can still leave a session', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);
    $player = makePlayer();

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'user_id' => $player->id,
        'payment_method' => 'digital_bank',
        'payment_status' => 'payment_sent',
        'joined_at' => now(),
    ]);

    $this->actingAs($player)
        ->deleteJson("/api/hubs/{$hub->id}/open-play/{$session->id}/leave")
        ->assertOk();

    expect(
        OpenPlayParticipant::where('open_play_session_id', $session->id)
            ->where('user_id', $player->id)
            ->where('payment_status', 'cancelled')
            ->exists()
    )->toBeTrue();
});

// ── Owner: Participant management ──────────────────────────────

it('owner can confirm a participant', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner       = makeOwner();
    $hub         = makeOwnerHub($owner);
    $court       = makeHubCourt($hub);
    $session     = makeOpenPlaySession($court, ['session' => ['max_players' => 1]]);
    $player      = makePlayer();

    $participant = OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'user_id'              => $player->id,
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'payment_sent',
        'joined_at'            => now(),
    ]);

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}/participants/{$participant->id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'confirmed');

    expect($participant->fresh()->payment_status)->toBe('confirmed');
});

it('owner can reject a participant receipt', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner       = makeOwner();
    $hub         = makeOwnerHub($owner);
    $court       = makeHubCourt($hub);
    $session     = makeOpenPlaySession($court, ['session' => ['max_players' => 1]]);
    $player      = makePlayer();

    $participant = OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'user_id'              => $player->id,
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'payment_sent',
        'receipt_image_url'    => 'https://example.com/receipt.jpg',
        'joined_at'            => now(),
    ]);

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}/participants/{$participant->id}/reject", [
            'payment_note' => 'Receipt is blurry.',
        ])
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'pending_payment');

    $fresh = $participant->fresh();
    expect($fresh->payment_status)->toBe('pending_payment');
    expect($fresh->payment_note)->toBe('Receipt is blurry.');
    expect($fresh->receipt_image_url)->toBeNull();
    expect($fresh->expires_at)->not->toBeNull();
});

it('owner can cancel a single participant', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner       = makeOwner();
    $hub         = makeOwnerHub($owner);
    $court       = makeHubCourt($hub);
    $session     = makeOpenPlaySession($court, ['session' => ['max_players' => 1]]);
    $player      = makePlayer();

    $participant = OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'user_id'              => $player->id,
        'payment_method'       => 'pay_on_site',
        'payment_status'       => 'confirmed',
        'joined_at'            => now(),
    ]);

    $this->actingAs($owner)
        ->deleteJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}/participants/{$participant->id}")
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'cancelled');

    expect($participant->fresh()->cancelled_by)->toBe('owner');
});

// ── CancelExpiredBookings ──────────────────────────────────────

it('CancelExpiredBookings cancels expired open play participants', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner       = makeOwner();
    $hub         = makeOwnerHub($owner);
    $court       = makeHubCourt($hub);
    $session     = makeOpenPlaySession($court, ['session' => ['max_players' => 1]]);
    $player      = makePlayer();

    $participant = OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'user_id'              => $player->id,
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'pending_payment',
        'expires_at'           => now()->subMinute(),
        'joined_at'            => now()->subHours(2),
    ]);

    $session->recalculateStatus();
    expect($session->fresh()->status)->toBe('full');

    Artisan::call('bookings:cancel-expired');

    expect($participant->fresh()->payment_status)->toBe('cancelled');
    expect($participant->fresh()->cancelled_by)->toBe('system');
    expect($session->fresh()->status)->toBe('open');
});

it('CancelExpiredBookings does not cancel non-expired participants', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner       = makeOwner();
    $hub         = makeOwnerHub($owner);
    $court       = makeHubCourt($hub);
    $session     = makeOpenPlaySession($court);
    $player      = makePlayer();

    $participant = OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'user_id'              => $player->id,
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'pending_payment',
        'expires_at'           => now()->addHour(),
        'joined_at'            => now(),
    ]);

    Artisan::call('bookings:cancel-expired');

    expect($participant->fresh()->payment_status)->toBe('pending_payment');
});

it('CancelExpiredBookings cancels payment_sent participants whose expires_at has passed', function () {
    Mail::fake();
    Event::fake([NotificationBroadcast::class, BookingSlotUpdated::class]);

    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['max_players' => 1]]);
    $player  = makePlayer();

    $participant = OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'user_id'              => $player->id,
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'payment_sent',
        'receipt_image_url'    => 'https://example.com/receipt.jpg',
        'expires_at'           => now()->subMinute(),
        'joined_at'            => now()->subHours(3),
    ]);

    $session->recalculateStatus();
    expect($session->fresh()->status)->toBe('full');

    Artisan::call('bookings:cancel-expired');

    expect($participant->fresh()->payment_status)->toBe('cancelled');
    expect($participant->fresh()->cancelled_by)->toBe('system');
    expect($session->fresh()->status)->toBe('open');
});

it('CancelExpiredBookings does not apply strikes for payment_sent expirations', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);
    $player  = makePlayer();

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'user_id'              => $player->id,
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'payment_sent',
        'receipt_image_url'    => 'https://example.com/receipt.jpg',
        'expires_at'           => now()->subMinute(),
        'joined_at'            => now()->subHours(3),
    ]);

    Artisan::call('bookings:cancel-expired');

    expect($player->fresh()->expired_booking_strikes)->toBe(0);
});

it('expired open play joins can trigger a booking ban that blocks a new authenticated open play join', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $player = makePlayer();

    foreach (range(1, 3) as $dayOffset) {
        $session = makeOpenPlaySession($court, [
            'booking' => [
                'start_time' => now()->addDays($dayOffset)->setHour(10)->setMinute(0)->setSecond(0),
                'end_time' => now()->addDays($dayOffset)->setHour(12)->setMinute(0)->setSecond(0),
            ],
        ]);

        OpenPlayParticipant::create([
            'open_play_session_id' => $session->id,
            'user_id' => $player->id,
            'payment_method' => 'digital_bank',
            'payment_status' => 'pending_payment',
            'expires_at' => now()->subMinute(),
            'joined_at' => now()->subHours(2),
        ]);
        Artisan::call('bookings:cancel-expired');
    }

    $targetSession = makeOpenPlaySession($court, [
        'booking' => [
            'start_time' => now()->addDays(5)->setHour(10)->setMinute(0)->setSecond(0),
            'end_time' => now()->addDays(5)->setHour(12)->setMinute(0)->setSecond(0),
        ],
    ]);

    $player = $player->fresh();

    expect($player->isBookingBanned())->toBeTrue();

    $this->actingAs($player, 'sanctum')
        ->postJson("/api/hubs/{$hub->id}/open-play/{$targetSession->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Your account is temporarily restricted from making new bookings.');
});

it('expired open play joins can trigger a guest ban that blocks a new verification request', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);

    foreach (range(1, 3) as $dayOffset) {
        $session = makeOpenPlaySession($court, [
            'booking' => [
                'start_time' => now()->addDays($dayOffset)->setHour(10)->setMinute(0)->setSecond(0),
                'end_time' => now()->addDays($dayOffset)->setHour(12)->setMinute(0)->setSecond(0),
            ],
            'session' => ['guests_can_join' => true],
        ]);

        OpenPlayParticipant::create([
            'open_play_session_id' => $session->id,
            'guest_name' => 'Guest Player',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '09171234567',
            'payment_method' => 'digital_bank',
            'payment_status' => 'pending_payment',
            'expires_at' => now()->subMinute(),
            'joined_at' => now()->subHours(2),
        ]);
        Artisan::call('bookings:cancel-expired');
    }

    $targetSession = makeOpenPlaySession($court, [
        'booking' => [
            'start_time' => now()->addDays(5)->setHour(10)->setMinute(0)->setSecond(0),
            'end_time' => now()->addDays(5)->setHour(12)->setMinute(0)->setSecond(0),
        ],
        'session' => ['guests_can_join' => true],
    ]);

    $this->postJson("/api/hubs/{$hub->id}/open-play/{$targetSession->id}/guest-verify", [
        'email' => 'guest@example.com',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'This email is temporarily restricted from making new bookings.');

    expect(GuestBookingPenalty::where('email', 'guest@example.com')->first()?->isBanned())->toBeTrue();
});

it('expires_at is set to end_time minus 1 hour on paid join', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);
    $player  = makePlayer();

    $endTime = $session->booking->end_time;

    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'digital_bank',
        ])
        ->assertCreated();

    $participant = OpenPlayParticipant::where('open_play_session_id', $session->id)->first();

    expect(abs($participant->expires_at->timestamp - $endTime->copy()->subHour()->timestamp))
        ->toBeLessThanOrEqual(2);
});

it('session-started notification is sent when start_time passes and not yet sent', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $player  = makePlayer();

    $session = makeOpenPlaySession($court, [
        'booking' => [
            'start_time' => now()->subMinutes(5),
            'end_time'   => now()->addHours(2),
        ],
    ]);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'user_id'              => $player->id,
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'confirmed',
        'joined_at'            => now()->subMinutes(10),
    ]);

    Artisan::call('bookings:cancel-expired');

    expect($session->fresh()->start_notification_sent_at)->not->toBeNull();
    expect($player->fresh()->notifications()->count())->toBe(1);
    expect($player->fresh()->notifications()->first()->data['activity_type'])->toBe('open_play_session_started');
});

it('session-started notification is only sent once', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $player  = makePlayer();

    $session = makeOpenPlaySession($court, [
        'booking' => [
            'start_time' => now()->subMinutes(5),
            'end_time'   => now()->addHours(2),
        ],
    ]);

    OpenPlayParticipant::create([
        'open_play_session_id' => $session->id,
        'user_id'              => $player->id,
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'confirmed',
        'joined_at'            => now()->subMinutes(10),
    ]);

    Artisan::call('bookings:cancel-expired');
    Artisan::call('bookings:cancel-expired');

    expect($player->fresh()->notifications()->count())->toBe(1);
});
