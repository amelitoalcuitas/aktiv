<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlaySession;
use App\Models\User;

// ── Helpers ────────────────────────────────────────────────────

function makeUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function makeCourt(): Court
{
    $hub = Hub::factory()->create(['is_approved' => true, 'is_active' => true]);
    return Court::factory()->create(['hub_id' => $hub->id]);
}

function makeBookingFor(User $user, Court $court, array $overrides = []): Booking
{
    return Booking::factory()->create(array_merge([
        'court_id'   => $court->id,
        'booked_by'  => $user->id,
        'created_by' => $user->id,
    ], $overrides));
}

function makeOpenPlaySessionFor(Court $court, array $bookingOverrides = [], array $sessionOverrides = []): OpenPlaySession
{
    $booking = Booking::factory()->create(array_merge([
        'court_id'       => $court->id,
        'booked_by'      => null,
        'created_by'     => null,
        'session_type'   => 'open_play',
        'status'         => 'confirmed',
        'booking_source' => 'owner_added',
        'total_price'    => 0,
    ], $bookingOverrides));

    return OpenPlaySession::query()->create(array_merge([
        'booking_id'       => $booking->id,
        'sport'            => 'badminton',
        'max_players'      => 8,
        'price_per_player' => 150,
        'guests_can_join'  => false,
        'status'           => 'open',
    ], $sessionOverrides));
}

function makeParticipantFor(User $user, OpenPlaySession $session, array $overrides = []): OpenPlayParticipant
{
    $timestamps = array_intersect_key($overrides, array_flip(['created_at', 'updated_at']));
    $participant = OpenPlayParticipant::query()->create(array_merge([
        'open_play_session_id' => $session->id,
        'user_id'              => $user->id,
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'pending_payment',
        'joined_at'            => now(),
        'expires_at'           => now()->addHour(),
    ], array_diff_key($overrides, $timestamps)));

    if ($timestamps !== []) {
        $participant->forceFill($timestamps)->save();
    }

    return $participant->fresh();
}

// ── Index ──────────────────────────────────────────────────────

it('requires authentication to list bookings', function () {
    $this->getJson('/api/user/bookings')->assertUnauthorized();
});

it('returns only the authenticated user\'s mixed booking items', function () {
    $user  = makeUser();
    $other = makeUser();
    $court = makeCourt();

    makeBookingFor($user,  $court);
    makeBookingFor($user,  $court);
    makeBookingFor($other, $court);
    $session = makeOpenPlaySessionFor($court);
    makeParticipantFor($user, $session);
    makeParticipantFor($other, $session);

    $this->actingAs($user)
        ->getJson('/api/user/bookings')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('filters mixed booking items by status', function () {
    $user  = makeUser();
    $court = makeCourt();

    makeBookingFor($user, $court, ['status' => 'pending_payment']);
    makeBookingFor($user, $court, ['status' => 'confirmed']);
    makeBookingFor($user, $court, ['status' => 'cancelled']);
    $session = makeOpenPlaySessionFor($court);
    makeParticipantFor($user, $session, ['payment_status' => 'confirmed']);
    makeParticipantFor($user, $session, ['payment_status' => 'cancelled']);

    $this->actingAs($user)
        ->getJson('/api/user/bookings?status=confirmed')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'confirmed')
        ->assertJsonPath('data.1.status', 'confirmed');
});

it('paginates mixed booking items with 10 per page', function () {
    $user  = makeUser();
    $court = makeCourt();

    Booking::factory()->count(8)->create([
        'court_id'   => $court->id,
        'booked_by'  => $user->id,
        'created_by' => $user->id,
    ]);
    $session = makeOpenPlaySessionFor($court);
    foreach (range(1, 7) as $index) {
        makeParticipantFor($user, $session, [
            'created_at' => now()->subMinutes($index),
            'updated_at' => now()->subMinutes($index),
        ]);
    }

    $response = $this->actingAs($user)
        ->getJson('/api/user/bookings')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('meta.last_page'))->toBe(2);
    expect($response->json('meta.total'))->toBe(15);
});

it('returns court and hub info in each mixed booking item', function () {
    $user  = makeUser();
    $court = makeCourt();
    makeBookingFor($user, $court);
    $session = makeOpenPlaySessionFor($court);
    makeParticipantFor($user, $session);

    $this->actingAs($user)
        ->getJson('/api/user/bookings')
        ->assertOk()
        ->assertJsonStructure(['data' => [['court' => ['id', 'name', 'hub' => ['id', 'name']]]]]);
});

it('includes open play joins in the mixed booking feed', function () {
    $user  = makeUser();
    $court = makeCourt();
    $session = makeOpenPlaySessionFor($court, [], ['max_players' => 12, 'price_per_player' => 220]);

    $participant = makeParticipantFor($user, $session, ['payment_status' => 'payment_sent']);

    $this->actingAs($user)
        ->getJson('/api/user/bookings')
        ->assertOk()
        ->assertJsonPath('data.0.id', $participant->id)
        ->assertJsonPath('data.0.entry_type', 'open_play_participant')
        ->assertJsonPath('data.0.session_id', $session->id)
        ->assertJsonPath('data.0.price_per_player', '220.00')
        ->assertJsonPath('data.0.max_players', 12);
});

it('excludes guest open play participants from the mixed booking feed', function () {
    $user  = makeUser();
    $court = makeCourt();
    $session = makeOpenPlaySessionFor($court);

    makeParticipantFor($user, $session);
    OpenPlayParticipant::query()->create([
        'open_play_session_id' => $session->id,
        'guest_name'           => 'Guest',
        'guest_email'          => 'guest@example.com',
        'guest_tracking_token' => (string) str()->uuid(),
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'pending_payment',
        'joined_at'            => now(),
        'expires_at'           => now()->addHour(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/user/bookings')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('finds the correct page for a booking item in the mixed feed', function () {
    $user  = makeUser();
    $court = makeCourt();

    $target = null;

    foreach (range(1, 11) as $index) {
        $booking = makeBookingFor($user, $court, [
            'created_at' => now()->subMinutes($index),
            'updated_at' => now()->subMinutes($index),
        ]);

        if ($index === 11) {
            $target = $booking;
        }
    }

    $this->actingAs($user)
        ->getJson("/api/user/bookings/page-of?item_id={$target->id}")
        ->assertOk()
        ->assertJsonPath('page', 2);
});

it('finds the correct page for an open play participant item in the mixed feed', function () {
    $user  = makeUser();
    $court = makeCourt();
    $session = makeOpenPlaySessionFor($court);

    foreach (range(1, 10) as $index) {
        makeBookingFor($user, $court, [
            'created_at' => now()->subMinutes($index),
            'updated_at' => now()->subMinutes($index),
        ]);
    }

    $participant = makeParticipantFor($user, $session, [
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $this->actingAs($user)
        ->getJson("/api/user/bookings/page-of?item_id={$participant->id}")
        ->assertOk()
        ->assertJsonPath('page', 2);
});

// ── Cancel ────────────────────────────────────────────────────

it('requires authentication to cancel a booking', function () {
    $court   = makeCourt();
    $booking = Booking::factory()->create(['court_id' => $court->id]);

    $this->postJson("/api/user/bookings/booking/{$booking->id}/cancel")->assertUnauthorized();
});

it('cancels a pending_payment booking', function () {
    $user    = makeUser();
    $court   = makeCourt();
    $booking = makeBookingFor($user, $court, ['status' => 'pending_payment']);

    $this->actingAs($user)
        ->postJson("/api/user/bookings/booking/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancelled_by', 'user')
        ->assertJsonPath('data.entry_type', 'booking');

    expect($booking->fresh()->status)->toBe('cancelled');
});

it('cancels a payment_sent booking', function () {
    $user    = makeUser();
    $court   = makeCourt();
    $booking = makeBookingFor($user, $court, [
        'status'              => 'payment_sent',
        'receipt_image_url'   => 'https://example.com/receipt.jpg',
        'receipt_uploaded_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson("/api/user/bookings/booking/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('cannot cancel a confirmed booking', function () {
    $user    = makeUser();
    $court   = makeCourt();
    $booking = makeBookingFor($user, $court, ['status' => 'confirmed']);

    $this->actingAs($user)
        ->postJson("/api/user/bookings/booking/{$booking->id}/cancel")
        ->assertUnprocessable();
});

it('cannot cancel another user\'s booking', function () {
    $user    = makeUser();
    $other   = makeUser();
    $court   = makeCourt();
    $booking = makeBookingFor($other, $court, ['status' => 'pending_payment']);

    $this->actingAs($user)
        ->postJson("/api/user/bookings/booking/{$booking->id}/cancel")
        ->assertForbidden();
});

it('cancels a pending open play participant through the mixed cancel endpoint', function () {
    $user  = makeUser();
    $court = makeCourt();
    $session = makeOpenPlaySessionFor($court);
    $participant = makeParticipantFor($user, $session, ['payment_status' => 'pending_payment']);

    $this->actingAs($user)
        ->postJson("/api/user/bookings/open_play_participant/{$participant->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.entry_type', 'open_play_participant')
        ->assertJsonPath('data.cancelled_by', 'user');

    expect($participant->fresh()->payment_status)->toBe('cancelled');
});

it('cancels a payment_sent open play participant through the mixed cancel endpoint', function () {
    $user  = makeUser();
    $court = makeCourt();
    $session = makeOpenPlaySessionFor($court);
    $participant = makeParticipantFor($user, $session, ['payment_status' => 'payment_sent']);

    $this->actingAs($user)
        ->postJson("/api/user/bookings/open_play_participant/{$participant->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('does not allow confirmed open play participants to be cancelled through the mixed endpoint', function () {
    $user  = makeUser();
    $court = makeCourt();
    $session = makeOpenPlaySessionFor($court);
    $participant = makeParticipantFor($user, $session, ['payment_status' => 'confirmed', 'expires_at' => null]);

    $this->actingAs($user)
        ->postJson("/api/user/bookings/open_play_participant/{$participant->id}/cancel")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This open play join cannot be cancelled.');
});
