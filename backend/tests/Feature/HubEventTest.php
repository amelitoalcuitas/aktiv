<?php

use App\Models\Court;
use App\Models\Hub;
use App\Models\HubEvent;
use App\Models\User;

// ── Helpers ──────────────────────────────────────────────────────

function makeOwnerWithHub(): array
{
    $owner = User::factory()->create(['role' => 'admin']);
    $hub   = Hub::factory()->create(['owner_id' => $owner->id, 'is_approved' => true, 'is_active' => true]);

    return [$owner, $hub];
}

function makeOtherOwner(): User
{
    return User::factory()->create(['role' => 'admin']);
}

// ── CRUD — Index ─────────────────────────────────────────────────

it('lists events for own hub', function () {
    [$owner, $hub] = makeOwnerWithHub();
    HubEvent::factory()->count(3)->create(['hub_id' => $hub->id]);

    $this->actingAs($owner)
        ->getJson("/api/dashboard/hubs/{$hub->id}/events")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('rejects non-owner from listing events', function () {
    [, $hub] = makeOwnerWithHub();
    $other   = makeOtherOwner();

    $this->actingAs($other)
        ->getJson("/api/dashboard/hubs/{$hub->id}/events")
        ->assertForbidden();
});

// ── CRUD — Store ─────────────────────────────────────────────────

it('creates an announcement event', function () {
    [$owner, $hub] = makeOwnerWithHub();

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/events", [
            'title'      => 'Holiday Closure',
            'event_type' => 'announcement',
            'date_from'  => now()->toDateString(),
            'date_to'    => now()->addDays(3)->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.event_type', 'announcement');

    expect(HubEvent::where('hub_id', $hub->id)->count())->toBe(1);
});

it('creates a promo event with discount', function () {
    [$owner, $hub] = makeOwnerWithHub();

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/events", [
            'title'          => 'Opening Promo',
            'event_type'     => 'promo',
            'date_from'      => now()->toDateString(),
            'date_to'        => now()->addDays(7)->toDateString(),
            'discount_type'  => 'percent',
            'discount_value' => 20,
        ])
        ->assertCreated()
        ->assertJsonPath('data.discount_type', 'percent')
        ->assertJsonPath('data.discount_value', '20.00');
});

it('rejects a promo event without discount fields', function () {
    [$owner, $hub] = makeOwnerWithHub();

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/events", [
            'title'      => 'Promo with no discount',
            'event_type' => 'promo',
            'date_from'  => now()->toDateString(),
            'date_to'    => now()->addDays(7)->toDateString(),
        ])
        ->assertUnprocessable();
});

it('rejects date_to before date_from', function () {
    [$owner, $hub] = makeOwnerWithHub();

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/events", [
            'title'      => 'Bad dates',
            'event_type' => 'announcement',
            'date_from'  => now()->addDays(5)->toDateString(),
            'date_to'    => now()->toDateString(),
        ])
        ->assertUnprocessable();
});

// ── CRUD — Update / Delete / Toggle ──────────────────────────────

it('updates an event', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $event = HubEvent::factory()->create(['hub_id' => $hub->id, 'title' => 'Old Title']);

    $this->actingAs($owner)
        ->putJson("/api/dashboard/hubs/{$hub->id}/events/{$event->id}", ['title' => 'New Title'])
        ->assertOk()
        ->assertJsonPath('data.title', 'New Title');
});

it('deletes an event', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $event = HubEvent::factory()->create(['hub_id' => $hub->id]);

    $this->actingAs($owner)
        ->deleteJson("/api/dashboard/hubs/{$hub->id}/events/{$event->id}")
        ->assertNoContent();

    expect(HubEvent::find($event->id))->toBeNull();
});

it('toggles event active status', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $event = HubEvent::factory()->create(['hub_id' => $hub->id, 'is_active' => true]);

    $this->actingAs($owner)
        ->patchJson("/api/dashboard/hubs/{$hub->id}/events/{$event->id}/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

// ── Booking Integration — Closure ────────────────────────────────

it('blocks booking when a closure event covers the slot', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $court = Court::factory()->create(['hub_id' => $hub->id, 'price_per_hour' => 300]);
    $user  = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

    HubEvent::factory()->closure()->create([
        'hub_id'    => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to'   => now('Asia/Manila')->addDays(2)->toDateString(),
        'is_active' => true,
    ]);

    $start = now('Asia/Manila')->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end   = $start->copy()->addHour();

    $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/bookings", [
            'start_time'     => $start->toIso8601String(),
            'end_time'       => $end->toIso8601String(),
            'session_type'   => 'private',
            'payment_method' => 'pay_on_site',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'unavailable'));
});

it('allows booking when closure event is inactive', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $court = Court::factory()->create(['hub_id' => $hub->id, 'price_per_hour' => 300, 'is_active' => true]);
    $user  = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

    HubEvent::factory()->closure()->create([
        'hub_id'    => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to'   => now('Asia/Manila')->addDays(2)->toDateString(),
        'is_active' => false, // inactive — should not block
    ]);

    $start = now('Asia/Manila')->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end   = $start->copy()->addHour();

    $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/bookings", [
            'start_time'     => $start->toIso8601String(),
            'end_time'       => $end->toIso8601String(),
            'session_type'   => 'private',
            'payment_method' => 'pay_on_site',
        ])
        ->assertCreated();
});

// ── Booking Integration — Promo ───────────────────────────────────

it('auto-applies percent promo discount to booking price', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $court = Court::factory()->create(['hub_id' => $hub->id, 'price_per_hour' => 400, 'is_active' => true]);
    $user  = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

    HubEvent::factory()->promo('percent', 25)->create([
        'hub_id'    => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to'   => now('Asia/Manila')->addDays(2)->toDateString(),
        'is_active' => true,
    ]);

    $start = now('Asia/Manila')->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end   = $start->copy()->addHour();

    $res = $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/bookings", [
            'start_time'     => $start->toIso8601String(),
            'end_time'       => $end->toIso8601String(),
            'session_type'   => 'private',
            'payment_method' => 'pay_on_site',
        ])
        ->assertCreated()
        ->json('data');

    // 400 * 0.75 = 300
    expect((float) $res['total_price'])->toBe(300.0);
    expect($res['applied_promo']['discount_type'])->toBe('percent');
});

it('auto-applies flat promo discount to booking price', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $court = Court::factory()->create(['hub_id' => $hub->id, 'price_per_hour' => 500, 'is_active' => true]);
    $user  = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

    HubEvent::factory()->promo('flat', 100)->create([
        'hub_id'    => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to'   => now('Asia/Manila')->addDays(2)->toDateString(),
        'is_active' => true,
    ]);

    $start = now('Asia/Manila')->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end   = $start->copy()->addHour();

    $res = $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/bookings", [
            'start_time'     => $start->toIso8601String(),
            'end_time'       => $end->toIso8601String(),
            'session_type'   => 'private',
            'payment_method' => 'pay_on_site',
        ])
        ->assertCreated()
        ->json('data');

    // 500 - 100 = 400
    expect((float) $res['total_price'])->toBe(400.0);
});

// ── Public API — Event flags on hub ──────────────────────────────

it('returns has_active_promo flag on hub show', function () {
    [$owner, $hub] = makeOwnerWithHub();

    HubEvent::factory()->promo()->create([
        'hub_id'    => $hub->id,
        'date_from' => now('Asia/Manila')->toDateString(),
        'date_to'   => now('Asia/Manila')->toDateString(),
        'is_active' => true,
    ]);

    $this->getJson("/api/hubs/{$hub->id}")
        ->assertOk()
        ->assertJsonPath('data.has_active_promo', true)
        ->assertJsonPath('data.has_active_announcement', false);
});

it('returns active_events on hub show', function () {
    [$owner, $hub] = makeOwnerWithHub();

    HubEvent::factory()->create([
        'hub_id'     => $hub->id,
        'event_type' => 'announcement',
        'title'      => 'Grand Opening',
        'date_from'  => now('Asia/Manila')->toDateString(),
        'date_to'    => now('Asia/Manila')->addDays(5)->toDateString(),
        'is_active'  => true,
    ]);

    $data = $this->getJson("/api/hubs/{$hub->id}")->assertOk()->json('data');

    expect($data['active_events'])->toHaveCount(1);
    expect($data['active_events'][0]['title'])->toBe('Grand Opening');
});
