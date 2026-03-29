<?php

use App\Models\Hub;

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
