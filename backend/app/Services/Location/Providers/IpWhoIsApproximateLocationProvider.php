<?php

namespace App\Services\Location\Providers;

use App\Services\Location\Contracts\ApproximateLocationProvider;
use Illuminate\Support\Facades\Http;

class IpWhoIsApproximateLocationProvider implements ApproximateLocationProvider
{
    public function lookup(string $ip): ?array
    {
        $baseUrl = rtrim((string) config('services.ip_geolocation.base_url', 'https://ipwho.is'), '/');
        $timeout = max(1, (int) config('services.ip_geolocation.timeout_seconds', 3));

        $request = Http::acceptJson()->timeout($timeout);

        $token = config('services.ip_geolocation.token');
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->get("{$baseUrl}/{$ip}");

        if (! $response->ok()) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        if (($payload['success'] ?? false) !== true) {
            return null;
        }

        $lat = isset($payload['latitude']) ? (float) $payload['latitude'] : null;
        $lng = isset($payload['longitude']) ? (float) $payload['longitude'] : null;

        if ($lat === null || $lng === null) {
            return null;
        }

        $timezone = $payload['timezone'] ?? null;
        $timezoneId = is_array($timezone) ? ($timezone['id'] ?? null) : null;

        return [
            'source' => 'ip',
            'accuracy' => 'approximate',
            'lat' => $lat,
            'lng' => $lng,
            'city' => $this->nullableString($payload['city'] ?? null),
            'region' => $this->nullableString($payload['region'] ?? null),
            'country' => $this->nullableString($payload['country'] ?? null),
            'timezone' => $this->nullableString($timezoneId),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
