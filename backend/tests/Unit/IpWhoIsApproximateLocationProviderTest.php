<?php

use App\Services\Location\Providers\IpWhoIsApproximateLocationProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.ip_geolocation.base_url', 'https://ipwho.is');
    config()->set('services.ip_geolocation.timeout_seconds', 3);
    config()->set('services.ip_geolocation.token', null);
});

it('maps a provider response into the canonical location payload', function () {
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

    $provider = new IpWhoIsApproximateLocationProvider();

    expect($provider->lookup('8.8.8.8'))->toBe([
        'source' => 'ip',
        'accuracy' => 'approximate',
        'lat' => 37.386,
        'lng' => -122.0838,
        'city' => 'Mountain View',
        'region' => 'California',
        'country' => 'United States',
        'timezone' => 'America/Los_Angeles',
    ]);
});

it('handles partial provider payloads safely', function () {
    Http::fake([
        'https://ipwho.is/1.1.1.1' => Http::response([
            'success' => true,
            'latitude' => -33.8688,
            'longitude' => 151.2093,
            'city' => '',
            'country' => 'Australia',
        ]),
    ]);

    $provider = new IpWhoIsApproximateLocationProvider();

    expect($provider->lookup('1.1.1.1'))->toBe([
        'source' => 'ip',
        'accuracy' => 'approximate',
        'lat' => -33.8688,
        'lng' => 151.2093,
        'city' => null,
        'region' => null,
        'country' => 'Australia',
        'timezone' => null,
    ]);
});
