<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HubDiscoveryRankingService
{
    public function supportsTrigramSearch(Builder $query): bool
    {
        return $query->getConnection()->getDriverName() !== 'sqlite';
    }

    public function applyDiscoveryColumns(Builder $query): Builder
    {
        $driver = $query->getConnection()->getDriverName();

        return $query
            ->select('hubs.*')
            ->selectRaw($this->ownerPremiumSql() . ' AS owner_is_premium')
            ->selectRaw($this->discoveryPrioritySql() . ' AS discovery_priority_score')
            ->selectRaw($this->topScoreSql($driver) . ' AS top_score');
    }

    /**
     * @return array{0: string|null, 1: array<int, string>}
     */
    public function preferredLocationOrder(Request $request): array
    {
        $preferredCity = $request->string('preferred_city')->trim()->value();
        $preferredProvince = $request->string('preferred_province')->trim()->value();
        $preferredCountry = $request->string('preferred_country')->trim()->value();

        if (! $preferredCity && ! $preferredProvince && ! $preferredCountry) {
            return [null, []];
        }

        $bindings = [];

        if ($preferredCity) {
            $bindings[] = mb_strtolower($preferredCity);
        }

        if ($preferredProvince) {
            $bindings[] = mb_strtolower($preferredProvince);
        }

        if ($preferredCountry) {
            $bindings[] = mb_strtolower($preferredCountry);
        }

        return [
            'CASE
                WHEN ' . ($preferredCity ? 'LOWER(city) = ?' : '1 = 0') . ' THEN 3
                WHEN ' . ($preferredProvince ? 'LOWER(province) = ?' : '1 = 0') . ' THEN 2
                WHEN ' . ($preferredCountry ? 'LOWER(country) = ?' : '1 = 0') . ' THEN 1
                ELSE 0
            END',
            $bindings,
        ];
    }

    public function distanceSql(): string
    {
        return '(6371 * acos(CASE
            WHEN (cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat))) > 1
                THEN 1
            ELSE (cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))
        END))';
    }

    public function applyListOrdering(
        Builder $query,
        ?string $search,
        ?float $lat,
        ?float $lng,
        ?string $sort,
        ?string $preferredLocationSql,
        array $preferredLocationBindings,
    ): Builder {
        if ($preferredLocationSql !== null) {
            $query->orderByRaw("{$preferredLocationSql} DESC", $preferredLocationBindings);
        }

        if ($search) {
            return $this->applySearchOrdering($query, $search, $lat, $lng);
        }

        if ($lat !== null && $lng !== null) {
            return $sort === 'top'
                ? $this->orderByTopScore($query, $lat, $lng)
                : $this->orderByDistance($query, $lat, $lng);
        }

        return match ($sort) {
            'courts_count' => $query
                ->orderByDesc('courts_count')
                ->orderByDesc('discovery_priority_score')
                ->orderByDesc('created_at'),
            'top' => $query
                ->orderByDesc('top_score')
                ->orderByDesc('discovery_priority_score')
                ->orderByDesc('created_at'),
            default => $query
                ->orderByDesc('created_at')
                ->orderByDesc('discovery_priority_score'),
        };
    }

    public function applySuggestionOrdering(
        Builder $query,
        string $search,
        ?float $lat,
        ?float $lng,
        ?string $preferredLocationSql,
        array $preferredLocationBindings,
    ): Builder {
        if ($preferredLocationSql !== null) {
            $query->orderByRaw("{$preferredLocationSql} DESC", $preferredLocationBindings);
        }

        return $this->applySearchOrdering($query, $search, $lat, $lng, true);
    }

    private function applySearchOrdering(
        Builder $query,
        string $search,
        ?float $lat,
        ?float $lng,
        bool $includeExtendedFields = false,
    ): Builder {
        $columns = ['name', 'city', 'province'];

        if ($includeExtendedFields) {
            $columns[] = 'address';
            $columns[] = 'description';
        }

        if ($this->supportsTrigramSearch($query)) {
            $searchScoreSql = 'GREATEST(' . implode(', ', array_map(
                fn (string $column): string => "word_similarity(?, {$column})",
                $columns
            )) . ')';
            $bindings = array_fill(0, count($columns), $search);
        } else {
            [$searchScoreSql, $bindings] = $this->sqliteSearchScore($search, $columns);
        }

        $query->orderByRaw("{$searchScoreSql} DESC", $bindings);
        $query->orderByDesc('discovery_priority_score');

        if ($lat !== null && $lng !== null) {
            $distanceSql = $this->distanceSql();

            return $query->orderByRaw("{$distanceSql} ASC", [$lat, $lng, $lat, $lat, $lng, $lat]);
        }

        return $query->orderByDesc('created_at');
    }

    private function orderByDistance(Builder $query, float $lat, float $lng): Builder
    {
        $distanceSql = $this->distanceSql();

        return $query
            ->orderByRaw("{$distanceSql} ASC", [$lat, $lng, $lat, $lat, $lng, $lat])
            ->orderByDesc('discovery_priority_score')
            ->orderByDesc('created_at');
    }

    private function orderByTopScore(Builder $query, float $lat, float $lng): Builder
    {
        $distanceSql = $this->distanceSql();

        return $query
            ->orderByDesc('top_score')
            ->orderByDesc('discovery_priority_score')
            ->orderByRaw("{$distanceSql} ASC", [$lat, $lng, $lat, $lat, $lng, $lat]);
    }

    /**
     * @param  array<int, string>  $columns
     * @return array{0: string, 1: array<int, string>}
     */
    private function sqliteSearchScore(string $search, array $columns): array
    {
        $needle = mb_strtolower($search);
        $like = '%' . $needle . '%';
        $weights = [
            'name' => [500, 250],
            'city' => [200, 120],
            'province' => [120, 80],
            'address' => [80, 50],
            'description' => [60, 40],
        ];

        $parts = [];
        $bindings = [];

        foreach ($columns as $column) {
            [$exactWeight, $likeWeight] = $weights[$column] ?? [40, 20];
            $parts[] = "(CASE WHEN LOWER({$column}) = ? THEN {$exactWeight} ELSE 0 END + CASE WHEN LOWER({$column}) LIKE ? THEN {$likeWeight} ELSE 0 END)";
            $bindings[] = $needle;
            $bindings[] = $like;
        }

        return [implode(' + ', $parts), $bindings];
    }

    private function ownerPremiumSql(): string
    {
        return 'COALESCE((
            SELECT CASE WHEN users.is_premium THEN 1 ELSE 0 END
            FROM users
            WHERE users.id = hubs.owner_id
            LIMIT 1
        ), 0)';
    }

    private function activeBoostSql(): string
    {
        return 'CASE
            WHEN discovery_boost_weight > 0
             AND (discovery_boost_expires_at IS NULL OR discovery_boost_expires_at >= CURRENT_TIMESTAMP)
                THEN discovery_boost_weight
            ELSE 0
        END';
    }

    private function discoveryPrioritySql(): string
    {
        return '(' . $this->ownerPremiumSql() . ' + ' . $this->activeBoostSql() . ')';
    }

    private function topScoreSql(string $driver): string
    {
        $recentBookingsThreshold = $driver === 'sqlite'
            ? "datetime(CURRENT_TIMESTAMP, '-30 days')"
            : "CURRENT_TIMESTAMP - INTERVAL '30 days'";

        return "
            (
                (5.0 * 3.5 + COALESCE((SELECT AVG(r.rating) FROM hub_ratings r WHERE r.hub_id = hubs.id), 3.5)
                           * COALESCE((SELECT COUNT(*) FROM hub_ratings r WHERE r.hub_id = hubs.id), 0))
                / (5.0 + COALESCE((SELECT COUNT(*) FROM hub_ratings r WHERE r.hub_id = hubs.id), 0))
            ) * 0.40
            + LN(1 + (
                SELECT COUNT(*) FROM bookings b
                JOIN courts ct ON ct.id = b.court_id
                WHERE ct.hub_id = hubs.id
                  AND b.status IN ('confirmed', 'completed', 'payment_sent')
                  AND b.created_at >= {$recentBookingsThreshold}
            )) * 0.30
            + LN(1 + COALESCE((SELECT COUNT(*) FROM hub_ratings r WHERE r.hub_id = hubs.id), 0)) * 0.15
            + CASE WHEN EXISTS (
                SELECT 1 FROM hub_events
                WHERE hub_id = hubs.id
                  AND is_active = true
                  AND event_type = 'promo'
                  AND start_time <= CURRENT_TIMESTAMP
                  AND end_time >= CURRENT_TIMESTAMP
              ) THEN 1 ELSE 0 END * 0.10
            + (
                CASE WHEN cover_image_url IS NOT NULL THEN 0.25 ELSE 0 END
                + CASE WHEN description IS NOT NULL AND description <> '' THEN 0.25 ELSE 0 END
                + CASE WHEN lat IS NOT NULL THEN 0.25 ELSE 0 END
                + CASE WHEN EXISTS (SELECT 1 FROM hub_operating_hours WHERE hub_id = hubs.id) THEN 0.125 ELSE 0 END
                + CASE WHEN EXISTS (SELECT 1 FROM hub_contact_numbers WHERE hub_id = hubs.id) THEN 0.125 ELSE 0 END
              ) * 0.05
        ";
    }
}
