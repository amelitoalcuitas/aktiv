<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\User;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

function makeOverviewOwner(): User
{
    return User::factory()->create(['role' => 'owner']);
}

function makeOverviewHub(User $owner, string $name = 'Hub'): Hub
{
    return Hub::factory()->create([
        'owner_id' => $owner->id,
        'name' => $name,
        'is_approved' => true,
        'is_active' => true,
    ]);
}

function makeOverviewCourt(Hub $hub, string $name = 'Court A'): Court
{
    return Court::factory()->create([
        'hub_id' => $hub->id,
        'name' => $name,
    ]);
}

function makeOverviewBooking(Court $court, array $overrides = []): Booking
{
    return Booking::factory()->create(array_merge([
        'court_id' => $court->id,
        'sport' => 'badminton',
    ], $overrides));
}

it('returns aggregate totals and per hub breakdowns for a multi hub owner', function () {
    $owner = makeOverviewOwner();
    $alphaHub = makeOverviewHub($owner, 'Alpha Hub');
    $betaHub = makeOverviewHub($owner, 'Beta Hub');
    $alphaCourt = makeOverviewCourt($alphaHub, 'Alpha Court');
    $betaCourt = makeOverviewCourt($betaHub, 'Beta Court');

    $today = Carbon::create(2026, 4, 2, 0, 0, 0, 'Asia/Manila');

    Carbon::setTestNow($today);

    makeOverviewBooking($alphaCourt, [
        'status' => 'payment_sent',
        'created_at' => $today->copy()->subMinutes(5)->utc(),
        'start_time' => $today->copy()->setTime(11, 0)->utc(),
        'end_time' => $today->copy()->setTime(12, 0)->utc(),
        'guest_name' => 'Review Alpha',
    ]);

    makeOverviewBooking($alphaCourt, [
        'status' => 'pending_payment',
        'created_at' => $today->copy()->subMinutes(10)->utc(),
        'start_time' => $today->copy()->setTime(13, 0)->utc(),
        'end_time' => $today->copy()->setTime(14, 0)->utc(),
        'total_price' => 300,
        'guest_name' => 'Pending Alpha',
    ]);

    makeOverviewBooking($alphaCourt, [
        'status' => 'confirmed',
        'created_at' => $today->copy()->subMinutes(15)->utc(),
        'start_time' => $today->copy()->setTime(9, 0)->utc(),
        'end_time' => $today->copy()->setTime(10, 0)->utc(),
        'total_price' => 400,
        'guest_name' => 'Morning Alpha',
    ]);

    makeOverviewBooking($betaCourt, [
        'status' => 'payment_sent',
        'created_at' => $today->copy()->subMinutes(1)->utc(),
        'start_time' => $today->copy()->setTime(15, 0)->utc(),
        'end_time' => $today->copy()->setTime(16, 0)->utc(),
        'guest_name' => 'Review Beta',
    ]);

    makeOverviewBooking($betaCourt, [
        'status' => 'confirmed',
        'created_at' => $today->copy()->subMinutes(20)->utc(),
        'start_time' => $today->copy()->setTime(17, 0)->utc(),
        'end_time' => $today->copy()->setTime(18, 0)->utc(),
        'total_price' => 600,
        'guest_name' => 'Evening Beta',
    ]);

    $response = $this->actingAs($owner)
        ->getJson('/api/dashboard/overview')
        ->assertOk()
        ->assertJsonPath('data.summary.needs_review_count', 2)
        ->assertJsonPath('data.summary.pending_payments_count', 1)
        ->assertJsonPath('data.summary.today_confirmed_count', 2)
        ->assertJsonPath('data.summary.revenue_today', 1000)
        ->assertJsonPath('data.action_needed.0.court.hub_id', $betaHub->id)
        ->assertJsonPath('data.action_needed.1.court.hub_id', $alphaHub->id)
        ->assertJsonPath('data.today_schedule.0.guest_name', 'Morning Alpha');

    $hubs = collect($response->json('data.hubs'))->keyBy('hub_id');

    expect($hubs[$alphaHub->id]['needs_review_count'])->toBe(1)
        ->and($hubs[$alphaHub->id]['pending_payments_count'])->toBe(1)
        ->and($hubs[$alphaHub->id]['today_confirmed_count'])->toBe(1)
        ->and($hubs[$alphaHub->id]['revenue_today'])->toBe(400)
        ->and($hubs[$betaHub->id]['needs_review_count'])->toBe(1)
        ->and($hubs[$betaHub->id]['pending_payments_count'])->toBe(0)
        ->and($hubs[$betaHub->id]['today_confirmed_count'])->toBe(1)
        ->and($hubs[$betaHub->id]['revenue_today'])->toBe(600);
});

it('only returns data from the authenticated owner hubs', function () {
    $owner = makeOverviewOwner();
    $otherOwner = makeOverviewOwner();
    $ownerHub = makeOverviewHub($owner, 'Own Hub');
    $otherHub = makeOverviewHub($otherOwner, 'Other Hub');
    $ownerCourt = makeOverviewCourt($ownerHub);
    $otherCourt = makeOverviewCourt($otherHub);

    Carbon::setTestNow(Carbon::create(2026, 4, 2, 0, 0, 0, 'Asia/Manila'));

    makeOverviewBooking($ownerCourt, [
        'status' => 'payment_sent',
        'start_time' => now('Asia/Manila')->setTime(10, 0)->utc(),
        'end_time' => now('Asia/Manila')->setTime(11, 0)->utc(),
    ]);

    makeOverviewBooking($otherCourt, [
        'status' => 'payment_sent',
        'start_time' => now('Asia/Manila')->setTime(12, 0)->utc(),
        'end_time' => now('Asia/Manila')->setTime(13, 0)->utc(),
    ]);

    $this->actingAs($owner)
        ->getJson('/api/dashboard/overview')
        ->assertOk()
        ->assertJsonPath('data.summary.needs_review_count', 1)
        ->assertJsonCount(1, 'data.hubs')
        ->assertJsonMissing(['hub_name' => 'Other Hub']);
});

it('filters today values using the Manila calendar date', function () {
    $owner = makeOverviewOwner();
    $hub = makeOverviewHub($owner, 'Boundary Hub');
    $court = makeOverviewCourt($hub);

    Carbon::setTestNow(Carbon::create(2026, 4, 2, 12, 0, 0, 'Asia/Manila'));

    makeOverviewBooking($court, [
        'status' => 'confirmed',
        'start_time' => Carbon::create(2026, 4, 2, 12, 0, 0, 'Asia/Manila')->utc(),
        'end_time' => Carbon::create(2026, 4, 2, 13, 0, 0, 'Asia/Manila')->utc(),
        'total_price' => 250,
    ]);

    makeOverviewBooking($court, [
        'status' => 'confirmed',
        'start_time' => Carbon::create(2026, 4, 1, 23, 0, 0, 'Asia/Manila')->utc(),
        'end_time' => Carbon::create(2026, 4, 1, 23, 59, 0, 'Asia/Manila')->utc(),
        'total_price' => 350,
    ]);

    makeOverviewBooking($court, [
        'status' => 'confirmed',
        'start_time' => Carbon::create(2026, 4, 3, 0, 0, 0, 'Asia/Manila')->utc(),
        'end_time' => Carbon::create(2026, 4, 3, 1, 0, 0, 'Asia/Manila')->utc(),
        'total_price' => 999,
    ]);

    $this->actingAs($owner)
        ->getJson('/api/dashboard/overview')
        ->assertOk()
        ->assertJsonPath('data.summary.today_confirmed_count', 1)
        ->assertJsonPath('data.summary.revenue_today', 250)
        ->assertJsonCount(1, 'data.today_schedule');
});

it('uses each hub timezone when classifying today schedule items', function () {
    $owner = makeOverviewOwner();
    $tokyoHub = makeOverviewHub($owner, 'Tokyo Hub');
    $tokyoHub->update(['timezone' => 'Asia/Tokyo']);
    $court = makeOverviewCourt($tokyoHub);

    Carbon::setTestNow(Carbon::create(2026, 4, 2, 16, 30, 0, 'UTC'));

    makeOverviewBooking($court, [
        'status' => 'confirmed',
        'start_time' => Carbon::create(2026, 4, 3, 0, 30, 0, 'Asia/Tokyo')->utc(),
        'end_time' => Carbon::create(2026, 4, 3, 1, 30, 0, 'Asia/Tokyo')->utc(),
        'total_price' => 500,
    ]);

    makeOverviewBooking($court, [
        'status' => 'confirmed',
        'start_time' => Carbon::create(2026, 4, 2, 0, 30, 0, 'Asia/Tokyo')->utc(),
        'end_time' => Carbon::create(2026, 4, 2, 1, 30, 0, 'Asia/Tokyo')->utc(),
        'total_price' => 999,
    ]);

    $this->actingAs($owner)
        ->getJson('/api/dashboard/overview')
        ->assertOk()
        ->assertJsonPath('data.summary.today_confirmed_count', 1)
        ->assertJsonPath('data.summary.revenue_today', 500)
        ->assertJsonPath('data.today_schedule.0.hub_timezone', 'Asia/Tokyo');
});
