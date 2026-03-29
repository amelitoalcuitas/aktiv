<?php

namespace App\Services\Location\Contracts;

interface ApproximateLocationProvider
{
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
    public function lookup(string $ip): ?array;
}
