<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
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

// ── Index ──────────────────────────────────────────────────────

it('requires authentication to list bookings', function () {
    $this->getJson('/api/user/bookings')->assertUnauthorized();
});

it('returns only the authenticated user\'s bookings', function () {
    $user  = makeUser();
    $other = makeUser();
    $court = makeCourt();

    makeBookingFor($user,  $court);
    makeBookingFor($user,  $court);
    makeBookingFor($other, $court);

    $this->actingAs($user)
        ->getJson('/api/user/bookings')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters bookings by status', function () {
    $user  = makeUser();
    $court = makeCourt();

    makeBookingFor($user, $court, ['status' => 'pending_payment']);
    makeBookingFor($user, $court, ['status' => 'confirmed']);
    makeBookingFor($user, $court, ['status' => 'cancelled']);

    $this->actingAs($user)
        ->getJson('/api/user/bookings?status=confirmed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'confirmed');
});

it('paginates bookings with 10 per page', function () {
    $user  = makeUser();
    $court = makeCourt();

    Booking::factory()->count(15)->create([
        'court_id'  => $court->id,
        'booked_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/user/bookings')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('meta.last_page'))->toBe(2);
    expect($response->json('meta.total'))->toBe(15);
});

it('returns court and hub info in each booking', function () {
    $user  = makeUser();
    $court = makeCourt();
    makeBookingFor($user, $court);

    $this->actingAs($user)
        ->getJson('/api/user/bookings')
        ->assertOk()
        ->assertJsonStructure(['data' => [['court' => ['id', 'name', 'hub' => ['id', 'name']]]]]);
});

// ── Cancel ────────────────────────────────────────────────────

it('requires authentication to cancel a booking', function () {
    $court   = makeCourt();
    $booking = Booking::factory()->create(['court_id' => $court->id]);

    $this->postJson("/api/user/bookings/{$booking->id}/cancel")->assertUnauthorized();
});

it('cancels a pending_payment booking', function () {
    $user    = makeUser();
    $court   = makeCourt();
    $booking = makeBookingFor($user, $court, ['status' => 'pending_payment']);

    $this->actingAs($user)
        ->postJson("/api/user/bookings/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancelled_by', 'user');

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
        ->postJson("/api/user/bookings/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('cannot cancel a confirmed booking', function () {
    $user    = makeUser();
    $court   = makeCourt();
    $booking = makeBookingFor($user, $court, ['status' => 'confirmed']);

    $this->actingAs($user)
        ->postJson("/api/user/bookings/{$booking->id}/cancel")
        ->assertUnprocessable();
});

it('cannot cancel another user\'s booking', function () {
    $user    = makeUser();
    $other   = makeUser();
    $court   = makeCourt();
    $booking = makeBookingFor($other, $court, ['status' => 'pending_payment']);

    $this->actingAs($user)
        ->postJson("/api/user/bookings/{$booking->id}/cancel")
        ->assertForbidden();
});
