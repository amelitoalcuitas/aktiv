<?php

namespace App\Services\Location;

use App\Services\Location\Contracts\ApproximateLocationProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApproximateLocationService
{
    public function __construct(
        private readonly ApproximateLocationProvider $provider
    ) {
    }

    /**
     * @return array{
     *     source: 'ip',
     *     accuracy: 'approximate',
     *     lat: float,
     *     lng: float,
     *     city: string|null,
     *     region: string|null,
     *     country: string|null,
     *     timezone: string|null
     * }|null
     */
    public function locate(string $ip): ?array
    {
        $normalizedIp = $this->normalizePublicIp($ip);

        if ($normalizedIp === null) {
            return null;
        }

        $ttl = max(60, (int) config('services.ip_geolocation.cache_ttl_seconds', 900));

        return Cache::remember(
            "approximate_location:{$normalizedIp}",
            now()->addSeconds($ttl),
            fn (): ?array => $this->provider->lookup($normalizedIp)
        );
    }

    public function extractClientIp(Request $request): ?string
    {
        foreach (array_unique(array_filter([$request->ip(), ...$request->ips()])) as $candidate) {
            $normalizedIp = $this->normalizePublicIp($candidate);

            if ($normalizedIp !== null) {
                return $normalizedIp;
            }
        }

        return null;
    }

    public function normalizePublicIp(?string $ip): ?string
    {
        if (! is_string($ip)) {
            return null;
        }

        $candidate = trim($ip);

        if ($candidate === '') {
            return null;
        }

        return filter_var(
            $candidate,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) ?: null;
    }
}
