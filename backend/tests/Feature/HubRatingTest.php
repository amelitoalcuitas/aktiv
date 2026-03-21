<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\HubRating;
use App\Models\User;

// ── Helpers ─────────────────────────────────────────────────────

function makeRatingUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function makeApprovedHub(): Hub
{
    return Hub::factory()->create(['is_approved' => true, 'is_active' => true]);
}

// ── Index (public) ───────────────────────────────────────────────

it('allows guests to list hub ratings', function () {
    $hub  = makeApprovedHub();
    $user = makeRatingUser();

    HubRating::factory()->create([
        'hub_id'  => $hub->id,
        'user_id' => $user->id,
        'rating'  => 5,
        'comment' => 'Great place!',
    ]);

    $this->getJson("/api/hubs/{$hub->id}/ratings")
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'rating', 'comment', 'created_at', 'user' => ['id', 'name', 'avatar_url']]]]);
});

it('returns ratings newest first', function () {
    $hub  = makeApprovedHub();
    $u1   = makeRatingUser();
    $u2   = makeRatingUser();

    HubRating::factory()->create(['hub_id' => $hub->id, 'user_id' => $u1->id, 'rating' => 3, 'created_at' => now()->subMinutes(10)]);
    HubRating::factory()->create(['hub_id' => $hub->id, 'user_id' => $u2->id, 'rating' => 5, 'created_at' => now()]);

    $data = $this->getJson("/api/hubs/{$hub->id}/ratings")->assertOk()->json('data');

    expect($data[0]['rating'])->toBe(5);
});

// ── Store (auth required) ────────────────────────────────────────

it('requires authentication to submit a rating', function () {
    $hub = makeApprovedHub();
    $this->postJson("/api/hubs/{$hub->id}/ratings", ['rating' => 4])->assertUnauthorized();
});

it('creates a rating for a hub', function () {
    $hub  = makeApprovedHub();
    $user = makeRatingUser();

    $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/ratings", ['rating' => 4, 'comment' => 'Nice courts'])
        ->assertCreated()
        ->assertJsonPath('data.rating', 4)
        ->assertJsonPath('data.comment', 'Nice courts');

    expect(HubRating::where('hub_id', $hub->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

it('validates rating is between 1 and 5', function () {
    $hub  = makeApprovedHub();
    $user = makeRatingUser();

    $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/ratings", ['rating' => 0])
        ->assertUnprocessable();

    $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/ratings", ['rating' => 6])
        ->assertUnprocessable();
});

it('allows comment to be omitted', function () {
    $hub  = makeApprovedHub();
    $user = makeRatingUser();

    $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/ratings", ['rating' => 3])
        ->assertCreated()
        ->assertJsonPath('data.comment', null);
});

it('updates existing rating instead of creating a duplicate', function () {
    $hub  = makeApprovedHub();
    $user = makeRatingUser();

    HubRating::factory()->create(['hub_id' => $hub->id, 'user_id' => $user->id, 'rating' => 2]);

    $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/ratings", ['rating' => 5, 'comment' => 'Changed my mind'])
        ->assertCreated()
        ->assertJsonPath('data.rating', 5);

    expect(HubRating::where('hub_id', $hub->id)->where('user_id', $user->id)->count())->toBe(1);
});

it('rejects a comment exceeding 1000 characters', function () {
    $hub  = makeApprovedHub();
    $user = makeRatingUser();

    $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/ratings", ['rating' => 3, 'comment' => str_repeat('a', 1001)])
        ->assertUnprocessable();
});

// ── Hub index/show include rating stats ──────────────────────────

it('returns rating and reviews_count in hub index', function () {
    $hub  = makeApprovedHub();
    $u1   = makeRatingUser();
    $u2   = makeRatingUser();

    HubRating::factory()->create(['hub_id' => $hub->id, 'user_id' => $u1->id, 'rating' => 4]);
    HubRating::factory()->create(['hub_id' => $hub->id, 'user_id' => $u2->id, 'rating' => 2]);

    $this->getJson('/api/hubs')
        ->assertOk()
        ->assertJsonFragment(['reviews_count' => 2]);
});

it('returns correct average rating in hub show', function () {
    $hub  = makeApprovedHub();
    $u1   = makeRatingUser();
    $u2   = makeRatingUser();

    HubRating::factory()->create(['hub_id' => $hub->id, 'user_id' => $u1->id, 'rating' => 4]);
    HubRating::factory()->create(['hub_id' => $hub->id, 'user_id' => $u2->id, 'rating' => 2]);

    $data = $this->getJson("/api/hubs/{$hub->id}")
        ->assertOk()
        ->json('data');

    expect((float) $data['rating'])->toBe(3.0);
    expect($data['reviews_count'])->toBe(2);
});

it('rejects booking_id that belongs to another user', function () {
    $hub    = makeApprovedHub();
    $user   = makeRatingUser();
    $other  = makeRatingUser();
    $court  = Court::factory()->create(['hub_id' => $hub->id]);
    $booking = Booking::factory()->create([
        'court_id'   => $court->id,
        'booked_by'  => $other->id,
        'created_by' => $other->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/ratings", ['rating' => 4, 'booking_id' => $booking->id])
        ->assertForbidden();
});
