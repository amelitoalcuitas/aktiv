<?php

use App\Enums\UserRole;
use App\Models\Hub;
use App\Models\User;
use Carbon\Carbon;

it('returns distinct approved active cities for explore filters', function () {
    Hub::factory()->create([
        'city' => 'Pagadian',
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Pagadian',
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Dipolog',
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Ozamiz',
        'is_approved' => false,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Dapitan',
        'is_approved' => true,
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/hubs/cities');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJson([
            'data' => [
                ['city' => 'Dipolog', 'distance_km' => null],
                ['city' => 'Pagadian', 'distance_km' => null],
            ],
            'meta' => [
                'total' => 2,
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
            ],
        ]);
});

it('sorts explore cities by nearest hub when coordinates are provided', function () {
    Hub::factory()->create([
        'city' => 'Pagadian',
        'lat' => 7.8257,
        'lng' => 123.4370,
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Dipolog',
        'lat' => 8.5883,
        'lng' => 123.3409,
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Dapitan',
        'lat' => null,
        'lng' => null,
        'is_approved' => true,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/hubs/cities?lat=7.8257&lng=123.4370');

    $response->assertOk()
        ->assertJsonPath('data.0.city', 'Pagadian')
        ->assertJsonPath('data.1.city', 'Dipolog')
        ->assertJsonPath('data.2.city', 'Dapitan');

    expect((float) $response->json('data.0.distance_km'))->toBe(0.0);
    expect($response->json('data.2.distance_km'))->toBeNull();
});

it('filters explore cities by search query', function () {
    Hub::factory()->create([
        'city' => 'Pagadian',
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Dipolog',
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Pagbilao',
        'is_approved' => true,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/hubs/cities?search=pag');

    $response->assertOk()
        ->assertJsonPath('meta.total', 2);

    expect(collect($response->json('data'))->pluck('city')->all())
        ->toBe(['Pagadian', 'Pagbilao']);
});

it('paginates explore cities', function () {
    foreach (['Dipolog', 'Iligan', 'Oroquieta'] as $city) {
        Hub::factory()->create([
            'city' => $city,
            'is_approved' => true,
            'is_active' => true,
        ]);
    }

    $response = $this->getJson('/api/hubs/cities?per_page=2&page=2');

    $response->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.city', 'Oroquieta');
});

it('preserves nearest-first ordering for searched cities with coordinates', function () {
    Hub::factory()->create([
        'city' => 'Pagadian',
        'lat' => 7.8257,
        'lng' => 123.4370,
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Pagbilao',
        'lat' => 13.9667,
        'lng' => 121.7000,
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Dipolog',
        'lat' => 8.5883,
        'lng' => 123.3409,
        'is_approved' => true,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/hubs/cities?search=pag&lat=7.8257&lng=123.4370');

    $response->assertOk()
        ->assertJsonPath('data.0.city', 'Pagadian')
        ->assertJsonPath('data.1.city', 'Pagbilao');
});

it('limits explore cities to a regional radius when requested', function () {
    Hub::factory()->create([
        'city' => 'Pagadian',
        'lat' => 7.8257,
        'lng' => 123.4370,
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Dipolog',
        'lat' => 8.5883,
        'lng' => 123.3409,
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Manila',
        'lat' => 14.5995,
        'lng' => 120.9842,
        'is_approved' => true,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/hubs/cities?lat=7.8257&lng=123.4370&radius=250');

    $response->assertOk()
        ->assertJsonPath('data.0.city', 'Pagadian')
        ->assertJsonPath('data.1.city', 'Dipolog');

    expect(collect($response->json('data'))->pluck('city')->all())
        ->not->toContain('Manila');
});

it('applies radius filtering with paginated city responses', function () {
    Hub::factory()->create([
        'city' => 'Pagadian',
        'lat' => 7.8257,
        'lng' => 123.4370,
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Dipolog',
        'lat' => 8.5883,
        'lng' => 123.3409,
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'city' => 'Manila',
        'lat' => 14.5995,
        'lng' => 120.9842,
        'is_approved' => true,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/hubs/cities?lat=7.8257&lng=123.4370&radius=250&per_page=1&page=1');

    $response->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.city', 'Pagadian');
});

it('keeps far away hubs when coordinates are provided without radius', function () {
    $nearHub = Hub::factory()->create([
        'name' => 'Pagadian Hub',
        'city' => 'Pagadian',
        'lat' => 7.8257,
        'lng' => 123.4370,
        'is_approved' => true,
        'is_active' => true,
    ]);

    $farHub = Hub::factory()->create([
        'name' => 'Manila Hub',
        'city' => 'Manila',
        'lat' => 14.5995,
        'lng' => 120.9842,
        'is_approved' => true,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/hubs?lat=7.8257&lng=123.4370');

    $response->assertOk()
        ->assertJsonPath('meta.total', 2);

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$nearHub->id, $farHub->id]);
});

it('applies an explicit radius to nearby hub queries', function () {
    $nearHub = Hub::factory()->create([
        'name' => 'Pagadian Hub',
        'city' => 'Pagadian',
        'lat' => 7.8257,
        'lng' => 123.4370,
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'name' => 'Manila Hub',
        'city' => 'Manila',
        'lat' => 14.5995,
        'lng' => 120.9842,
        'is_approved' => true,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/hubs?lat=7.8257&lng=123.4370&radius=50');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $nearHub->id);
});

it('biases hub ordering by preferred city, province, and country without filtering results out', function () {
    $sameCity = Hub::factory()->create([
        'name' => 'Pagadian Hub',
        'city' => 'Pagadian',
        'province' => 'Zamboanga del Sur',
        'country' => 'Philippines',
        'is_approved' => true,
        'is_active' => true,
    ]);

    $sameProvince = Hub::factory()->create([
        'name' => 'Aurora Hub',
        'city' => 'Aurora',
        'province' => 'Zamboanga del Sur',
        'country' => 'Philippines',
        'is_approved' => true,
        'is_active' => true,
    ]);

    $sameCountry = Hub::factory()->create([
        'name' => 'Cebu Hub',
        'city' => 'Cebu City',
        'province' => 'Cebu',
        'country' => 'Philippines',
        'is_approved' => true,
        'is_active' => true,
    ]);

    $otherCountry = Hub::factory()->create([
        'name' => 'Singapore Hub',
        'city' => 'Singapore',
        'province' => 'Central Region',
        'country' => 'Singapore',
        'is_approved' => true,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/hubs?preferred_city=Pagadian&preferred_province=Zamboanga%20del%20Sur&preferred_country=Philippines');

    $response->assertOk()
        ->assertJsonPath('meta.total', 4);

    expect(collect($response->json('data'))->pluck('id')->take(4)->all())
        ->toBe([$sameCity->id, $sameProvince->id, $sameCountry->id, $otherCountry->id]);
});

it('prioritizes premium-owned hubs when the base default ordering ties', function () {
    $timestamp = Carbon::parse('2026-03-31 09:00:00');
    $premiumOwner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_premium' => true,
    ]);
    $standardOwner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_premium' => false,
    ]);

    $standardHub = Hub::factory()->create([
        'owner_id' => $standardOwner->id,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $premiumHub = Hub::factory()->create([
        'owner_id' => $premiumOwner->id,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $response = $this->getJson('/api/hubs');

    $response->assertOk()
        ->assertJsonPath('meta.total', 2);

    expect(collect($response->json('data'))->pluck('id')->take(2)->all())
        ->toBe([$premiumHub->id, $standardHub->id]);
});

it('keeps a closer non-premium hub ahead of a farther premium hub in distance ordering', function () {
    $premiumOwner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_premium' => true,
    ]);

    $nearHub = Hub::factory()->create([
        'name' => 'Near Standard Hub',
        'lat' => 7.8257,
        'lng' => 123.4370,
        'is_approved' => true,
        'is_active' => true,
    ]);

    $farPremiumHub = Hub::factory()->create([
        'owner_id' => $premiumOwner->id,
        'name' => 'Far Premium Hub',
        'lat' => 14.5995,
        'lng' => 120.9842,
        'is_approved' => true,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/hubs?lat=7.8257&lng=123.4370');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $nearHub->id)
        ->assertJsonPath('data.1.id', $farPremiumHub->id);
});

it('keeps stronger search matches ahead of premium hubs', function () {
    $premiumOwner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_premium' => true,
    ]);

    $strongMatch = Hub::factory()->create([
        'name' => 'Pagadian Prime',
        'city' => 'Pagadian',
        'province' => 'Zamboanga del Sur',
        'is_approved' => true,
        'is_active' => true,
    ]);

    $premiumHub = Hub::factory()->create([
        'owner_id' => $premiumOwner->id,
        'name' => 'Pagadian Prime Courts',
        'city' => 'Aurora',
        'province' => 'Zamboanga del Sur',
        'is_approved' => true,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/hubs?search=Pagadian%20Prime');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $strongMatch->id)
        ->assertJsonPath('data.1.id', $premiumHub->id);
});

it('uses premium as a secondary tie breaker when sorting by top score', function () {
    $timestamp = Carbon::parse('2026-03-31 09:00:00');
    $premiumOwner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_premium' => true,
    ]);
    $standardOwner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_premium' => false,
    ]);

    $standardHub = Hub::factory()->create([
        'owner_id' => $standardOwner->id,
        'description' => null,
        'cover_image_url' => null,
        'lat' => null,
        'lng' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $premiumHub = Hub::factory()->create([
        'owner_id' => $premiumOwner->id,
        'description' => null,
        'cover_image_url' => null,
        'lat' => null,
        'lng' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $response = $this->getJson('/api/hubs?sort=top');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $premiumHub->id)
        ->assertJsonPath('data.1.id', $standardHub->id);
});

it('lets active discovery boosts outrank premium-only hubs when primary signals tie', function () {
    $timestamp = Carbon::parse('2026-03-31 09:00:00');
    $premiumOwner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_premium' => true,
    ]);
    $standardOwner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_premium' => false,
    ]);

    $premiumHub = Hub::factory()->create([
        'owner_id' => $premiumOwner->id,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $boostedHub = Hub::factory()->create([
        'owner_id' => $standardOwner->id,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
        'discovery_boost_weight' => 2,
        'discovery_boost_expires_at' => now()->addDays(5),
    ]);

    $response = $this->getJson('/api/hubs');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $boostedHub->id)
        ->assertJsonPath('data.1.id', $premiumHub->id);
});

it('ignores expired discovery boosts', function () {
    $timestamp = Carbon::parse('2026-03-31 09:00:00');
    $premiumOwner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_premium' => true,
    ]);
    $standardOwner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_premium' => false,
    ]);

    $premiumHub = Hub::factory()->create([
        'owner_id' => $premiumOwner->id,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $expiredBoostHub = Hub::factory()->create([
        'owner_id' => $standardOwner->id,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
        'discovery_boost_weight' => 3,
        'discovery_boost_expires_at' => Carbon::parse('2026-03-30 23:59:59'),
    ]);

    $response = $this->getJson('/api/hubs');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $premiumHub->id)
        ->assertJsonPath('data.1.id', $expiredBoostHub->id);
});

it('still excludes inactive and unapproved hubs even when they are promoted', function () {
    $premiumOwner = User::factory()->create([
        'role' => UserRole::Owner,
        'is_premium' => true,
    ]);

    $visibleHub = Hub::factory()->create([
        'name' => 'Visible Hub',
        'is_approved' => true,
        'is_active' => true,
    ]);

    Hub::factory()->create([
        'owner_id' => $premiumOwner->id,
        'name' => 'Inactive Premium Hub',
        'is_approved' => true,
        'is_active' => false,
        'discovery_boost_weight' => 4,
        'discovery_boost_expires_at' => Carbon::parse('2026-04-05 00:00:00'),
    ]);

    Hub::factory()->create([
        'owner_id' => $premiumOwner->id,
        'name' => 'Unapproved Premium Hub',
        'is_approved' => false,
        'is_active' => true,
        'discovery_boost_weight' => 4,
        'discovery_boost_expires_at' => Carbon::parse('2026-04-05 00:00:00'),
    ]);

    $response = $this->getJson('/api/hubs');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $visibleHub->id);
});
