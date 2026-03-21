<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\HubRating;
use App\Models\User;

// ── Helpers ─────────────────────────────────────────────────────

function makePendingReviewUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function makePendingReviewCourt(): Court
{
    $hub = Hub::factory()->create(['is_approved' => true, 'is_active' => true]);
    return Court::factory()->create(['hub_id' => $hub->id]);
}

function makePastBooking(User $user, Court $court, array $overrides = []): Booking
{
    return Booking::factory()->create(array_merge([
        'court_id'   => $court->id,
        'booked_by'  => $user->id,
        'created_by' => $user->id,
        'start_time' => now()->subDay()->setHour(10)->setMinute(0)->setSecond(0),
        'end_time'   => now()->subDay()->setHour(12)->setMinute(0)->setSecond(0),
        'status'     => 'confirmed',
    ], $overrides));
}

// ── Authentication ───────────────────────────────────────────────

it('requires authentication', function () {
    $this->getJson('/api/user/pending-review')->assertUnauthorized();
});

// ── Returns null when no pending review ─────────────────────────

it('returns null when user has no bookings', function () {
    $user = makePendingReviewUser();

    $this->actingAs($user)
        ->getJson('/api/user/pending-review')
        ->assertOk()
        ->assertJsonPath('booking', null);
});

it('returns null when booking end_time is in the future', function () {
    $user  = makePendingReviewUser();
    $court = makePendingReviewCourt();

    Booking::factory()->create([
        'court_id'   => $court->id,
        'booked_by'  => $user->id,
        'created_by' => $user->id,
        'start_time' => now()->addHour(),
        'end_time'   => now()->addHours(2),
        'status'     => 'confirmed',
    ]);

    $this->actingAs($user)
        ->getJson('/api/user/pending-review')
        ->assertOk()
        ->assertJsonPath('booking', null);
});

it('returns null when booking is cancelled', function () {
    $user  = makePendingReviewUser();
    $court = makePendingReviewCourt();

    makePastBooking($user, $court, ['status' => 'cancelled']);

    $this->actingAs($user)
        ->getJson('/api/user/pending-review')
        ->assertOk()
        ->assertJsonPath('booking', null);
});

it('returns null when user already rated the hub', function () {
    $user  = makePendingReviewUser();
    $court = makePendingReviewCourt();
    $booking = makePastBooking($user, $court);

    HubRating::factory()->create([
        'hub_id'  => $court->hub_id,
        'user_id' => $user->id,
        'rating'  => 4,
    ]);

    $this->actingAs($user)
        ->getJson('/api/user/pending-review')
        ->assertOk()
        ->assertJsonPath('booking', null);
});

// ── Returns the booking when eligible ───────────────────────────

it('returns unreviewed confirmed booking from yesterday', function () {
    $user    = makePendingReviewUser();
    $court   = makePendingReviewCourt();
    $booking = makePastBooking($user, $court, ['status' => 'confirmed']);

    $response = $this->actingAs($user)
        ->getJson('/api/user/pending-review')
        ->assertOk();

    expect($response->json('booking.id'))->toBe($booking->id);
    expect($response->json('booking.court.hub.name'))->not->toBeNull();
});

it('returns unreviewed completed booking from yesterday', function () {
    $user    = makePendingReviewUser();
    $court   = makePendingReviewCourt();
    $booking = makePastBooking($user, $court, ['status' => 'completed']);

    $response = $this->actingAs($user)
        ->getJson('/api/user/pending-review')
        ->assertOk();

    expect($response->json('booking.id'))->toBe($booking->id);
});

it('does not return another user\'s booking', function () {
    $user  = makePendingReviewUser();
    $other = makePendingReviewUser();
    $court = makePendingReviewCourt();

    makePastBooking($other, $court, ['status' => 'confirmed']);

    $this->actingAs($user)
        ->getJson('/api/user/pending-review')
        ->assertOk()
        ->assertJsonPath('booking', null);
});

// ── Test shortcut (local env) ────────────────────────────────────

it('test_booking_id shortcut returns booking bypassing time window', function () {
    $user  = makePendingReviewUser();
    $court = makePendingReviewCourt();

    // Booking 3 days ago — outside normal 24h window
    $booking = Booking::factory()->create([
        'court_id'   => $court->id,
        'booked_by'  => $user->id,
        'created_by' => $user->id,
        'start_time' => now()->subDays(3)->setHour(10),
        'end_time'   => now()->subDays(3)->setHour(12),
        'status'     => 'confirmed',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/api/user/pending-review?test_booking_id={$booking->id}")
        ->assertOk();

    expect($response->json('booking.id'))->toBe($booking->id);
});

it('test_booking_id shortcut returns null when already rated', function () {
    $user  = makePendingReviewUser();
    $court = makePendingReviewCourt();

    $booking = Booking::factory()->create([
        'court_id'   => $court->id,
        'booked_by'  => $user->id,
        'created_by' => $user->id,
        'start_time' => now()->subDays(3)->setHour(10),
        'end_time'   => now()->subDays(3)->setHour(12),
        'status'     => 'confirmed',
    ]);

    HubRating::factory()->create([
        'hub_id'  => $court->hub_id,
        'user_id' => $user->id,
        'rating'  => 4,
    ]);

    $this->actingAs($user)
        ->getJson("/api/user/pending-review?test_booking_id={$booking->id}")
        ->assertOk()
        ->assertJsonPath('booking', null);
});
