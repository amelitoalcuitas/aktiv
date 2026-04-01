<?php

use App\Console\Commands\CancelExpiredBookings;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\HubEvent;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlaySession;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

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
    return Hub::factory()->create(['owner_id' => $owner->id, 'is_approved' => true, 'is_active' => true]);
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
            'court_id'         => $court->id,
            'start_time'       => $start,
            'end_time'         => $end,
            'sport'            => 'badminton',
            'max_players'      => 8,
            'price_per_player' => 150,
            'guests_can_join'  => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.sport', 'badminton');

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
            'court_id'         => $otherCourt->id,
            'start_time'       => now()->addDay()->setHour(10)->toIso8601String(),
            'end_time'         => now()->addDay()->setHour(12)->toIso8601String(),
            'sport'            => 'badminton',
            'max_players'      => 8,
            'price_per_player' => 150,
        ])
        ->assertUnprocessable();
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

    $this->actingAs($owner)
        ->getJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $session->id)
        ->assertJsonPath('data.booking_id', $session->booking_id)
        ->assertJsonPath('data.booking.court.id', $court->id)
        ->assertJsonPath('data.participants_count', 1)
        ->assertJsonPath('data.confirmed_participants_count', 1);
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
            'court_id' => $court->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'sport' => 'pickleball',
            'max_players' => 10,
            'price_per_player' => 200,
            'notes' => 'Bring water.',
            'guests_can_join' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.sport', 'pickleball')
        ->assertJsonPath('data.max_players', 10)
        ->assertJsonPath('data.price_per_player', '200.00')
        ->assertJsonPath('data.notes', 'Bring water.')
        ->assertJsonPath('data.guests_can_join', true)
        ->assertJsonPath('data.booking.start_time', $start->toIso8601String())
        ->assertJsonPath('data.booking.end_time', $end->toIso8601String());

    expect($session->fresh()->sport)->toBe('pickleball');
    expect($session->fresh()->max_players)->toBe(10);
    expect($session->fresh()->price_per_player)->toBe('200.00');
    expect($session->booking->fresh()->sport)->toBe('pickleball');
});

it('owner can move an open play session to another available slot and court', function () {
    $owner = makeOwner();
    $hub = makeOwnerHub($owner);
    $court = makeHubCourt($hub);
    $targetCourt = makeHubCourt($hub);
    $session = makeOpenPlaySession($court);

    $start = now()->addDays(3)->setHour(16)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHours(2);

    $this->actingAs($owner)
        ->putJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}", [
            'court_id' => $targetCourt->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'sport' => 'badminton',
            'max_players' => 8,
            'price_per_player' => 150,
            'notes' => null,
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
            'court_id' => $court->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'sport' => 'badminton',
            'max_players' => 8,
            'price_per_player' => 150,
            'notes' => null,
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
            'court_id' => $court->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'sport' => 'badminton',
            'max_players' => 8,
            'price_per_player' => 150,
            'notes' => null,
            'guests_can_join' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This court is unavailable: Maintenance');
});

it('owner cannot reduce max players below confirmed participants', function () {
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
        'payment_status' => 'confirmed',
        'joined_at' => now(),
    ]);

    $this->actingAs($owner)
        ->putJson("/api/dashboard/hubs/{$hub->id}/open-play/{$session->id}", [
            'court_id' => $court->id,
            'start_time' => $session->booking->start_time->toIso8601String(),
            'end_time' => $session->booking->end_time->toIso8601String(),
            'sport' => 'badminton',
            'max_players' => 1,
            'price_per_player' => 150,
            'notes' => null,
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
            'court_id'         => $court->id,
            'start_time'       => $start->toIso8601String(),
            'end_time'         => $end->toIso8601String(),
            'sport'            => 'badminton',
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
            'court_id'         => $court->id,
            'start_time'       => now()->addDay()->setHour(10)->toIso8601String(),
            'end_time'         => now()->addDay()->setHour(12)->toIso8601String(),
            'sport'            => 'badminton',
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

it('session becomes full when confirmed participant count reaches max_players', function () {
    $owner   = makeOwner();
    $hub     = makeOwnerHub($owner);
    $court   = makeHubCourt($hub);
    $session = makeOpenPlaySession($court, ['session' => ['max_players' => 1, 'price_per_player' => 0]]);
    $player  = makePlayer();

    $this->actingAs($player)
        ->postJson("/api/hubs/{$hub->id}/open-play/{$session->id}/join", [
            'payment_method' => 'pay_on_site',
        ])
        ->assertCreated();

    expect($session->fresh()->status)->toBe('full');
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
    $session = makeOpenPlaySession($court, ['session' => ['price_per_player' => 0, 'max_players' => 1]]);
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

// ── Owner: Participant management ──────────────────────────────

it('owner can confirm a participant', function () {
    $owner       = makeOwner();
    $hub         = makeOwnerHub($owner);
    $court       = makeHubCourt($hub);
    $session     = makeOpenPlaySession($court);
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
    $owner       = makeOwner();
    $hub         = makeOwnerHub($owner);
    $court       = makeHubCourt($hub);
    $session     = makeOpenPlaySession($court);
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
    $owner       = makeOwner();
    $hub         = makeOwnerHub($owner);
    $court       = makeHubCourt($hub);
    $session     = makeOpenPlaySession($court);
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
        'expires_at'           => now()->subMinute(),
        'joined_at'            => now()->subHours(2),
    ]);

    Artisan::call('bookings:cancel-expired');

    expect($participant->fresh()->payment_status)->toBe('cancelled');
    expect($participant->fresh()->cancelled_by)->toBe('system');
});

it('CancelExpiredBookings does not cancel non-expired participants', function () {
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
