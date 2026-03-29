<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.ip_geolocation.provider', 'ipwhois');
    config()->set('services.ip_geolocation.base_url', 'https://ipwho.is');
    config()->set('services.ip_geolocation.cache_ttl_seconds', 900);

    Cache::flush();
});

it('returns normalized approximate location data for a public forwarded ip', function () {
    Http::fake([
        'https://ipwho.is/8.8.8.8' => Http::response([
            'success' => true,
            'latitude' => 37.386,
            'longitude' => -122.0838,
            'city' => 'Mountain View',
            'region' => 'California',
            'country' => 'United States',
            'timezone' => ['id' => 'America/Los_Angeles'],
        ]),
    ]);

    $response = $this->withHeaders([
        'X-Forwarded-For' => '8.8.8.8',
    ])->getJson('/api/location/approx');

    $response->assertOk()->assertJson([
        'data' => [
            'source' => 'ip',
            'accuracy' => 'approximate',
            'lat' => 37.386,
            'lng' => -122.0838,
            'city' => 'Mountain View',
            'region' => 'California',
            'country' => 'United States',
            'timezone' => 'America/Los_Angeles',
        ],
    ]);
});

it('uses the first trusted public forwarded ip', function () {
    Http::fake([
        'https://ipwho.is/1.1.1.1' => Http::response([
            'success' => true,
            'latitude' => -33.8688,
            'longitude' => 151.2093,
            'city' => 'Sydney',
            'region' => 'New South Wales',
            'country' => 'Australia',
            'timezone' => ['id' => 'Australia/Sydney'],
        ]),
    ]);

    $this->withHeaders([
        'X-Forwarded-For' => '10.0.0.2, 1.1.1.1',
    ])->getJson('/api/location/approx')->assertOk();

    Http::assertSent(fn ($request) => $request->url() === 'https://ipwho.is/1.1.1.1');
});

it('returns null and skips provider lookup for private or missing ips', function () {
    Http::fake();

    $this->getJson('/api/location/approx')
        ->assertOk()
        ->assertExactJson(['data' => null]);

    $this->withHeaders([
        'X-Forwarded-For' => '192.168.1.10',
    ])->getJson('/api/location/approx')
        ->assertOk()
        ->assertExactJson(['data' => null]);

    Http::assertNothingSent();
});

it('returns null when the provider lookup fails', function () {
    Http::fake([
        'https://ipwho.is/8.8.4.4' => Http::response([
            'success' => false,
        ], 200),
    ]);

    $this->withHeaders([
        'X-Forwarded-For' => '8.8.4.4',
    ])->getJson('/api/location/approx')
        ->assertOk()
        ->assertExactJson(['data' => null]);
});

it('caches approximate location lookups by normalized ip', function () {
    Http::fake([
        'https://ipwho.is/8.8.8.8' => Http::response([
            'success' => true,
            'latitude' => 37.386,
            'longitude' => -122.0838,
            'city' => 'Mountain View',
            'region' => 'California',
            'country' => 'United States',
            'timezone' => ['id' => 'America/Los_Angeles'],
        ]),
    ]);

    $headers = ['X-Forwarded-For' => '8.8.8.8'];

    $this->withHeaders($headers)->getJson('/api/location/approx')->assertOk();
    $this->withHeaders($headers)->getJson('/api/location/approx')->assertOk();

    Http::assertSentCount(1);
});
