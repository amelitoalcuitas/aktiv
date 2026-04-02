<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\HubEvent;
use App\Models\HubSettings;
use App\Models\User;

// ── Helpers ──────────────────────────────────────────────────────

function makeOwnerWithHub(): array
{
    $owner = User::factory()->create(['role' => 'owner']);
    $hub   = Hub::factory()->create(['owner_id' => $owner->id, 'is_approved' => true, 'is_active' => true]);

    return [$owner, $hub];
}

function makeOtherOwner(): User
{
    return User::factory()->create(['role' => 'owner']);
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

it('filters events by overlapping date range', function () {
    [$owner, $hub] = makeOwnerWithHub();

    HubEvent::factory()->create([
        'hub_id' => $hub->id,
        'title' => 'Previous month event',
        'date_from' => '2026-03-29',
        'date_to' => '2026-03-31',
    ]);

    HubEvent::factory()->create([
        'hub_id' => $hub->id,
        'title' => 'Visible month event',
        'date_from' => '2026-04-02',
        'date_to' => '2026-04-05',
    ]);

    HubEvent::factory()->create([
        'hub_id' => $hub->id,
        'title' => 'Crosses into visible month',
        'date_from' => '2026-03-31',
        'date_to' => '2026-04-02',
    ]);

    $this->actingAs($owner)
        ->getJson("/api/dashboard/hubs/{$hub->id}/events?date_from=2026-04-01&date_to=2026-04-30")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['title' => 'Visible month event'])
        ->assertJsonFragment(['title' => 'Crosses into visible month'])
        ->assertJsonMissing(['title' => 'Previous month event']);
});

it('rejects non-owner from listing events', function () {
    [, $hub] = makeOwnerWithHub();
    $other   = makeOtherOwner();

    $this->actingAs($other)
        ->getJson("/api/dashboard/hubs/{$hub->id}/events")
        ->assertForbidden();
});

it('shows a single event for own hub', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $event = HubEvent::factory()->create([
        'hub_id' => $hub->id,
        'title' => 'Featured Event',
    ]);

    $this->actingAs($owner)
        ->getJson("/api/dashboard/hubs/{$hub->id}/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $event->id)
        ->assertJsonPath('data.title', 'Featured Event');
});

it('rejects non-owner from viewing a single event', function () {
    [, $hub] = makeOwnerWithHub();
    $event = HubEvent::factory()->create(['hub_id' => $hub->id]);
    $other = makeOtherOwner();

    $this->actingAs($other)
        ->getJson("/api/dashboard/hubs/{$hub->id}/events/{$event->id}")
        ->assertForbidden();
});

it('returns not found when viewing an event from another hub', function () {
    [$owner, $hub] = makeOwnerWithHub();
    [, $otherHub] = makeOwnerWithHub();
    $event = HubEvent::factory()->create(['hub_id' => $otherHub->id]);

    $this->actingAs($owner)
        ->getJson("/api/dashboard/hubs/{$hub->id}/events/{$event->id}")
        ->assertNotFound();
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

it('creates a voucher event with announcement details', function () {
    [$owner, $hub] = makeOwnerWithHub();

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/events", [
            'title' => 'Summer Voucher',
            'description' => 'Use this code on checkout.',
            'event_type' => 'voucher',
            'date_from' => now()->toDateString(),
            'date_to' => now()->addDays(7)->toDateString(),
            'discount_type' => 'flat',
            'discount_value' => 150,
            'voucher_code' => 'save1234abcd',
            'show_announcement' => true,
            'limit_total_uses' => true,
            'max_total_uses' => 50,
            'limit_per_user_uses' => true,
            'max_uses_per_user' => 2,
        ])
        ->assertCreated()
        ->assertJsonPath('data.event_type', 'voucher')
        ->assertJsonPath('data.voucher_code', 'SAVE1234ABCD')
        ->assertJsonPath('data.show_announcement', true)
        ->assertJsonPath('data.limit_total_uses', true)
        ->assertJsonPath('data.max_total_uses', 50)
        ->assertJsonPath('data.limit_per_user_uses', true)
        ->assertJsonPath('data.max_uses_per_user', 2);
});

it('requires a title even when voucher announcement is off', function () {
    [$owner, $hub] = makeOwnerWithHub();

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/events", [
            'event_type' => 'voucher',
            'date_from' => now()->toDateString(),
            'date_to' => now()->addDays(7)->toDateString(),
            'discount_type' => 'percent',
            'discount_value' => 20,
            'voucher_code' => 'SAVE12345678',
            'show_announcement' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');
});

it('stores voucher title and description even when announcement is off', function () {
    [$owner, $hub] = makeOwnerWithHub();

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/events", [
            'title' => 'Private Voucher',
            'description' => 'Hidden from the about page.',
            'event_type' => 'voucher',
            'date_from' => now()->toDateString(),
            'date_to' => now()->addDays(7)->toDateString(),
            'discount_type' => 'percent',
            'discount_value' => 20,
            'voucher_code' => 'SAVE12345678',
            'show_announcement' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Private Voucher')
        ->assertJsonPath('data.description', 'Hidden from the about page.')
        ->assertJsonPath('data.show_announcement', false);
});

it('rejects duplicate voucher codes within the same hub', function () {
    [$owner, $hub] = makeOwnerWithHub();
    HubEvent::factory()->voucher(voucherCode: 'SAVE12345678')->create(['hub_id' => $hub->id]);

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/events", [
            'event_type' => 'voucher',
            'date_from' => now()->toDateString(),
            'date_to' => now()->addDays(7)->toDateString(),
            'discount_type' => 'percent',
            'discount_value' => 20,
            'voucher_code' => 'SAVE12345678',
            'show_announcement' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('voucher_code');
});

it('rejects voucher limits when enabled without values', function () {
    [$owner, $hub] = makeOwnerWithHub();

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/events", [
            'title' => 'Limited Voucher',
            'event_type' => 'voucher',
            'date_from' => now()->toDateString(),
            'date_to' => now()->addDays(7)->toDateString(),
            'discount_type' => 'percent',
            'discount_value' => 20,
            'voucher_code' => 'SAVE12345678',
            'limit_total_uses' => true,
            'limit_per_user_uses' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['max_total_uses', 'max_uses_per_user']);
});

it('allows the same voucher code on a different hub', function () {
    [$owner, $hub] = makeOwnerWithHub();
    [$otherOwner, $otherHub] = makeOwnerWithHub();
    HubEvent::factory()->voucher(voucherCode: 'SAVE12345678')->create(['hub_id' => $hub->id]);

    $this->actingAs($otherOwner)
        ->postJson("/api/dashboard/hubs/{$otherHub->id}/events", [
            'title' => 'Second Hub Voucher',
            'event_type' => 'voucher',
            'date_from' => now()->toDateString(),
            'date_to' => now()->addDays(7)->toDateString(),
            'discount_type' => 'percent',
            'discount_value' => 20,
            'voucher_code' => 'SAVE12345678',
            'show_announcement' => false,
        ])
        ->assertCreated();
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
    expect($res['applied_discount']['discount_type'])->toBe('percent');
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

it('previews a valid voucher for the selected booking', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $court = Court::factory()->create(['hub_id' => $hub->id, 'price_per_hour' => 400, 'is_active' => true]);

    HubEvent::factory()->voucher('percent', 25, 'SAVE12345678')->create([
        'hub_id' => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to' => now('Asia/Manila')->addDays(2)->toDateString(),
        'is_active' => true,
        'show_announcement' => false,
        'title' => null,
        'description' => null,
    ]);

    $start = now('Asia/Manila')->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHour();

    $this->postJson("/api/hubs/{$hub->id}/vouchers/preview", [
        'voucher_code' => 'save12345678',
        'items' => [[
            'court_id' => $court->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
        ]],
    ])
        ->assertOk()
        ->assertJsonPath('data.voucher_code', 'SAVE12345678')
        ->assertJsonPath('data.summary.original_total', 400)
        ->assertJsonPath('data.summary.discounted_total', 300)
        ->assertJsonPath('data.applied_discount.source', 'voucher');
});

it('rejects voucher preview when total-use cap is exhausted', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $court = Court::factory()->create(['hub_id' => $hub->id, 'price_per_hour' => 400, 'is_active' => true]);
    $voucher = HubEvent::factory()->voucher('percent', 25, 'SAVE12345678')->create([
        'hub_id' => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to' => now('Asia/Manila')->addDays(2)->toDateString(),
        'is_active' => true,
        'show_announcement' => false,
        'title' => 'Limited Voucher',
        'description' => null,
        'limit_total_uses' => true,
        'max_total_uses' => 1,
    ]);

    Booking::factory()->create([
        'court_id' => $court->id,
        'status' => 'confirmed',
        'expires_at' => null,
        'applied_hub_event_id' => $voucher->id,
    ]);

    $start = now('Asia/Manila')->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHour();

    $this->postJson("/api/hubs/{$hub->id}/vouchers/preview", [
        'voucher_code' => 'SAVE12345678',
        'items' => [[
            'court_id' => $court->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
        ]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('voucher_code');
});

it('rejects voucher preview when guest email has reached the per-user cap', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $court = Court::factory()->create(['hub_id' => $hub->id, 'price_per_hour' => 400, 'is_active' => true]);
    $voucher = HubEvent::factory()->voucher('percent', 25, 'SAVE12345678')->create([
        'hub_id' => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to' => now('Asia/Manila')->addDays(2)->toDateString(),
        'is_active' => true,
        'show_announcement' => false,
        'title' => 'Email Limited Voucher',
        'description' => null,
        'limit_per_user_uses' => true,
        'max_uses_per_user' => 1,
    ]);

    Booking::factory()->create([
        'court_id' => $court->id,
        'status' => 'confirmed',
        'expires_at' => null,
        'guest_email' => 'guest@example.com',
        'applied_hub_event_id' => $voucher->id,
    ]);

    $start = now('Asia/Manila')->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHour();

    $this->postJson("/api/hubs/{$hub->id}/vouchers/preview", [
        'voucher_code' => 'SAVE12345678',
        'guest_email' => 'guest@example.com',
        'items' => [[
            'court_id' => $court->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
        ]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('voucher_code');
});

it('does not count expired bookings toward voucher usage caps', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $court = Court::factory()->create(['hub_id' => $hub->id, 'price_per_hour' => 400, 'is_active' => true]);
    $voucher = HubEvent::factory()->voucher('percent', 25, 'SAVE12345678')->create([
        'hub_id' => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to' => now('Asia/Manila')->addDays(2)->toDateString(),
        'is_active' => true,
        'show_announcement' => false,
        'title' => 'Temporary Voucher',
        'description' => null,
        'limit_total_uses' => true,
        'max_total_uses' => 1,
    ]);

    Booking::factory()->create([
        'court_id' => $court->id,
        'status' => 'pending_payment',
        'expires_at' => now()->subMinute(),
        'applied_hub_event_id' => $voucher->id,
    ]);

    $start = now('Asia/Manila')->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHour();

    $this->postJson("/api/hubs/{$hub->id}/vouchers/preview", [
        'voucher_code' => 'SAVE12345678',
        'items' => [[
            'court_id' => $court->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
        ]],
    ])
        ->assertOk();
});

it('rejects voucher preview outside the voucher time window', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $court = Court::factory()->create(['hub_id' => $hub->id, 'price_per_hour' => 400, 'is_active' => true]);

    HubEvent::factory()->voucher('percent', 25, 'SAVE12345678')->create([
        'hub_id' => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to' => now('Asia/Manila')->addDays(2)->toDateString(),
        'time_from' => '09:00',
        'time_to' => '11:00',
        'is_active' => true,
        'show_announcement' => false,
        'title' => null,
        'description' => null,
    ]);

    $start = now('Asia/Manila')->addDay()->setHour(12)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHour();

    $this->postJson("/api/hubs/{$hub->id}/vouchers/preview", [
        'voucher_code' => 'SAVE12345678',
        'items' => [[
            'court_id' => $court->id,
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
        ]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('voucher_code');
});

it('applies voucher discount instead of promo for registered bookings', function () {
    [$owner, $hub] = makeOwnerWithHub();
    $court = Court::factory()->create(['hub_id' => $hub->id, 'price_per_hour' => 400, 'is_active' => true]);
    $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);

    HubEvent::factory()->promo('percent', 10)->create([
        'hub_id' => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to' => now('Asia/Manila')->addDays(2)->toDateString(),
        'is_active' => true,
    ]);

    HubEvent::factory()->voucher('flat', 150, 'SAVE12345678')->create([
        'hub_id' => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to' => now('Asia/Manila')->addDays(2)->toDateString(),
        'is_active' => true,
        'show_announcement' => false,
        'title' => null,
        'description' => null,
    ]);

    $start = now('Asia/Manila')->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHour();

    $res = $this->actingAs($user)
        ->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/bookings", [
            'start_time' => $start->toIso8601String(),
            'end_time' => $end->toIso8601String(),
            'session_type' => 'private',
            'payment_method' => 'pay_on_site',
            'voucher_code' => 'SAVE12345678',
        ])
        ->assertCreated()
        ->json('data');

    expect((float) $res['total_price'])->toBe(250.0);
    expect($res['applied_discount']['source'])->toBe('voucher');
    expect(Booking::query()->latest('created_at')->first()?->applied_hub_event_id)->not->toBeNull();
});

it('applies voucher discount for guest bookings', function () {
    [$owner, $hub] = makeOwnerWithHub();
    HubSettings::factory()->create([
        'hub_id' => $hub->id,
        'require_account_to_book' => false,
        'payment_methods' => ['pay_on_site'],
    ]);
    $court = Court::factory()->create(['hub_id' => $hub->id, 'price_per_hour' => 500, 'is_active' => true]);

    HubEvent::factory()->voucher('flat', 100, 'SAVE12345678')->create([
        'hub_id' => $hub->id,
        'date_from' => now('Asia/Manila')->subDay()->toDateString(),
        'date_to' => now('Asia/Manila')->addDays(2)->toDateString(),
        'is_active' => true,
        'show_announcement' => false,
        'title' => null,
        'description' => null,
    ]);

    $start = now('Asia/Manila')->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHour();

    $this->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/guest-verify", [
        'email' => 'guest@example.com',
    ])->assertOk();

    cache()->put("guest_otp:{$hub->id}:guest@example.com", '123456', now()->addMinutes(10));

    $res = $this->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/guest-bookings", [
        'email' => 'guest@example.com',
        'otp' => '123456',
        'guest_name' => 'Guest User',
        'guest_phone' => '09170000000',
        'start_time' => $start->toIso8601String(),
        'end_time' => $end->toIso8601String(),
        'session_type' => 'private',
        'payment_method' => 'pay_on_site',
        'voucher_code' => 'SAVE12345678',
    ])
        ->assertCreated()
        ->json('data');

    expect((float) $res['total_price'])->toBe(400.0);
    expect($res['applied_discount']['source'])->toBe('voucher');
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
