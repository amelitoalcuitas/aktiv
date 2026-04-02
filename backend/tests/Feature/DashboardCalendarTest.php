<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\HubEvent;
use App\Models\OpenPlaySession;
use App\Models\User;

function makeDashboardOwner(): User
{
    return User::factory()->create(['role' => 'owner']);
}

function makeDashboardHub(User $owner, string $name = 'Hub'): Hub
{
    return Hub::factory()->create([
        'owner_id' => $owner->id,
        'name' => $name,
        'is_approved' => true,
        'is_active' => true,
    ]);
}

function makeDashboardCourt(Hub $hub): Court
{
    return Court::factory()->create(['hub_id' => $hub->id]);
}

function makeDashboardOpenPlaySession(Court $court, array $overrides = []): OpenPlaySession
{
    $start = now('Asia/Manila')->addDay()->setTime(19, 0)->utc();
    $end = now('Asia/Manila')->addDay()->setTime(21, 0)->utc();

    $booking = Booking::create(array_merge([
        'court_id' => $court->id,
        'sport' => 'badminton',
        'start_time' => $start,
        'end_time' => $end,
        'session_type' => 'open_play',
        'status' => 'confirmed',
        'booking_source' => 'owner_added',
        'total_price' => 0,
    ], $overrides['booking'] ?? []));

    return OpenPlaySession::create(array_merge([
        'booking_id' => $booking->id,
        'title' => 'Evening Open Play',
        'sport' => 'badminton',
        'max_players' => 8,
        'price_per_player' => 150.00,
        'guests_can_join' => false,
        'status' => 'open',
    ], $overrides['session'] ?? []));
}

it('returns combined calendar items across owned hubs', function () {
    $owner = makeDashboardOwner();
    $alphaHub = makeDashboardHub($owner, 'Alpha Hub');
    $betaHub = makeDashboardHub($owner, 'Beta Hub');
    $betaCourt = makeDashboardCourt($betaHub);

    $event = HubEvent::factory()->create([
        'hub_id' => $alphaHub->id,
        'title' => 'League Night',
        'date_from' => '2026-05-10',
        'date_to' => '2026-05-10',
        'time_from' => '18:00:00',
        'time_to' => '20:00:00',
        'is_active' => true,
    ]);

    $session = makeDashboardOpenPlaySession($betaCourt, [
        'booking' => [
            'start_time' => now('Asia/Manila')->setDate(2026, 5, 11)->setTime(19, 0)->utc(),
            'end_time' => now('Asia/Manila')->setDate(2026, 5, 11)->setTime(21, 0)->utc(),
        ],
    ]);

    $this->actingAs($owner)
        ->getJson('/api/dashboard/calendar?date_from=2026-05-01&date_to=2026-05-31')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', "event:{$event->id}:2026-05-10")
        ->assertJsonPath('data.0.kind', 'event')
        ->assertJsonPath('data.0.hub_name', 'Alpha Hub')
        ->assertJsonPath('data.0.time_label', '6:00 PM-8:00 PM')
        ->assertJsonPath('data.0.to', "/dashboard/hubs/{$alphaHub->id}/events")
        ->assertJsonPath('data.1.id', "open-play:{$session->id}")
        ->assertJsonPath('data.1.kind', 'open_play')
        ->assertJsonPath('data.1.hub_name', 'Beta Hub')
        ->assertJsonPath('data.1.date', '2026-05-11')
        ->assertJsonPath('data.1.time_label', '7:00 PM-9:00 PM')
        ->assertJsonPath('data.1.to', "/dashboard/hubs/{$betaHub->id}/open-play");
});

it('filters calendar items by overlapping range and expands multi day events inside the requested range', function () {
    $owner = makeDashboardOwner();
    $hub = makeDashboardHub($owner, 'Range Hub');
    $court = makeDashboardCourt($hub);

    $event = HubEvent::factory()->create([
        'hub_id' => $hub->id,
        'title' => 'Long Weekend Event',
        'date_from' => '2026-04-29',
        'date_to' => '2026-05-02',
        'is_active' => true,
    ]);

    $session = makeDashboardOpenPlaySession($court, [
        'booking' => [
            'start_time' => now('Asia/Manila')->setDate(2026, 5, 1)->setTime(20, 0)->utc(),
            'end_time' => now('Asia/Manila')->setDate(2026, 5, 1)->setTime(21, 0)->utc(),
        ],
    ]);

    makeDashboardOpenPlaySession($court, [
        'booking' => [
            'start_time' => now('Asia/Manila')->setDate(2026, 6, 1)->setTime(20, 0)->utc(),
            'end_time' => now('Asia/Manila')->setDate(2026, 6, 1)->setTime(21, 0)->utc(),
        ],
    ]);

    $response = $this->actingAs($owner)
        ->getJson('/api/dashboard/calendar?date_from=2026-05-01&date_to=2026-05-31')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment(['id' => "event:{$event->id}:2026-05-01"])
        ->assertJsonFragment(['id' => "event:{$event->id}:2026-05-02"])
        ->assertJsonFragment(['id' => "open-play:{$session->id}"]);

    expect(collect($response->json('data'))->pluck('date')->all())
        ->toBe(['2026-05-01', '2026-05-01', '2026-05-02']);
});

it('excludes inactive events and cancelled or completed open play records', function () {
    $owner = makeDashboardOwner();
    $hub = makeDashboardHub($owner, 'Filtered Hub');
    $court = makeDashboardCourt($hub);

    HubEvent::factory()->create([
        'hub_id' => $hub->id,
        'title' => 'Inactive Event',
        'date_from' => '2026-05-05',
        'date_to' => '2026-05-05',
        'is_active' => false,
    ]);

    makeDashboardOpenPlaySession($court, [
        'booking' => [
            'start_time' => now('Asia/Manila')->setDate(2026, 5, 7)->setTime(18, 0)->utc(),
            'end_time' => now('Asia/Manila')->setDate(2026, 5, 7)->setTime(20, 0)->utc(),
        ],
        'session' => ['status' => 'cancelled'],
    ]);

    makeDashboardOpenPlaySession($court, [
        'booking' => [
            'status' => 'completed',
            'start_time' => now('Asia/Manila')->setDate(2026, 5, 8)->setTime(18, 0)->utc(),
            'end_time' => now('Asia/Manila')->setDate(2026, 5, 8)->setTime(20, 0)->utc(),
        ],
    ]);

    $visibleSession = makeDashboardOpenPlaySession($court, [
        'booking' => [
            'start_time' => now('Asia/Manila')->setDate(2026, 5, 9)->setTime(18, 0)->utc(),
            'end_time' => now('Asia/Manila')->setDate(2026, 5, 9)->setTime(20, 0)->utc(),
        ],
        'session' => ['title' => 'Visible Session'],
    ]);

    $this->actingAs($owner)
        ->getJson('/api/dashboard/calendar?date_from=2026-05-01&date_to=2026-05-31')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', "open-play:{$visibleSession->id}")
        ->assertJsonMissing(['title' => 'Inactive Event']);
});

it('only returns items from the authenticated owner hubs', function () {
    $owner = makeDashboardOwner();
    $otherOwner = makeDashboardOwner();
    $ownerHub = makeDashboardHub($owner, 'Own Hub');
    $otherHub = makeDashboardHub($otherOwner, 'Other Hub');
    $ownerCourt = makeDashboardCourt($ownerHub);
    $otherCourt = makeDashboardCourt($otherHub);

    HubEvent::factory()->create([
        'hub_id' => $ownerHub->id,
        'title' => 'Own Event',
        'date_from' => '2026-05-05',
        'date_to' => '2026-05-05',
        'is_active' => true,
    ]);

    HubEvent::factory()->create([
        'hub_id' => $otherHub->id,
        'title' => 'Other Event',
        'date_from' => '2026-05-05',
        'date_to' => '2026-05-05',
        'is_active' => true,
    ]);

    makeDashboardOpenPlaySession($ownerCourt, [
        'session' => ['title' => 'Own Session'],
        'booking' => [
            'start_time' => now('Asia/Manila')->setDate(2026, 5, 6)->setTime(18, 0)->utc(),
            'end_time' => now('Asia/Manila')->setDate(2026, 5, 6)->setTime(20, 0)->utc(),
        ],
    ]);

    makeDashboardOpenPlaySession($otherCourt, [
        'session' => ['title' => 'Other Session'],
        'booking' => [
            'start_time' => now('Asia/Manila')->setDate(2026, 5, 6)->setTime(18, 0)->utc(),
            'end_time' => now('Asia/Manila')->setDate(2026, 5, 6)->setTime(20, 0)->utc(),
        ],
    ]);

    $this->actingAs($owner)
        ->getJson('/api/dashboard/calendar?date_from=2026-05-01&date_to=2026-05-31')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['title' => 'Own Event'])
        ->assertJsonFragment(['title' => 'Own Session'])
        ->assertJsonMissing(['title' => 'Other Event'])
        ->assertJsonMissing(['title' => 'Other Session']);
});

it('formats open play items using the hub timezone instead of a hardcoded Manila date', function () {
    $owner = makeDashboardOwner();
    $hub = makeDashboardHub($owner, 'Tokyo Hub');
    $hub->update(['timezone' => 'Asia/Tokyo']);
    $court = makeDashboardCourt($hub);

    $session = makeDashboardOpenPlaySession($court, [
        'booking' => [
            'start_time' => \Carbon\Carbon::create(2026, 5, 11, 0, 30, 0, 'Asia/Tokyo')->utc(),
            'end_time' => \Carbon\Carbon::create(2026, 5, 11, 2, 0, 0, 'Asia/Tokyo')->utc(),
        ],
    ]);

    $this->actingAs($owner)
        ->getJson('/api/dashboard/calendar?date_from=2026-05-01&date_to=2026-05-31')
        ->assertOk()
        ->assertJsonFragment([
            'id' => "open-play:{$session->id}",
            'date' => '2026-05-11',
            'time_label' => '12:30 AM-2:00 AM',
            'hub_timezone' => 'Asia/Tokyo',
        ]);
});
