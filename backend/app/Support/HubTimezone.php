<?php

namespace App\Support;

use App\Models\Hub;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class HubTimezone
{
    public const DEFAULT_TIMEZONE = 'Asia/Manila';

    public static function resolve(?string $timezone): string
    {
        return is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : self::DEFAULT_TIMEZONE;
    }

    public static function forHub(?Hub $hub): string
    {
        return self::resolve($hub?->timezone);
    }

    public static function todayStartUtc(string $timezone): Carbon
    {
        return now(self::resolve($timezone))->startOfDay()->utc();
    }

    public static function todayEndUtc(string $timezone): Carbon
    {
        return now(self::resolve($timezone))->endOfDay()->utc();
    }

    public static function startOfDayUtc(string $date, string $timezone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $date, self::resolve($timezone))
            ->startOfDay()
            ->utc();
    }

    public static function endOfDayUtc(string $date, string $timezone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $date, self::resolve($timezone))
            ->endOfDay()
            ->utc();
    }

    public static function localDate(CarbonInterface $dateTime, string $timezone): string
    {
        return $dateTime->copy()->setTimezone(self::resolve($timezone))->toDateString();
    }

    public static function localTime(CarbonInterface $dateTime, string $timezone, string $format = 'g:i A'): string
    {
        return $dateTime->copy()->setTimezone(self::resolve($timezone))->format($format);
    }

    /**
     * @param  Collection<int, Hub>  $hubs
     * @return array<string, array{start: Carbon, end: Carbon}>
     */
    public static function todayRangesByHub(Collection $hubs): array
    {
        return $hubs->mapWithKeys(function (Hub $hub): array {
            $timezone = self::forHub($hub);

            return [
                (string) $hub->id => [
                    'start' => self::todayStartUtc($timezone),
                    'end' => self::todayEndUtc($timezone),
                ],
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Hub>  $hubs
     * @return array{start: Carbon, end: Carbon}
     */
    public static function encompassingTodayRange(Collection $hubs): array
    {
        $ranges = self::todayRangesByHub($hubs);

        $starts = array_column($ranges, 'start');
        $ends = array_column($ranges, 'end');

        return [
            'start' => collect($starts)->sortBy(fn (Carbon $value) => $value->getTimestamp())->first() ?? self::todayStartUtc(self::DEFAULT_TIMEZONE),
            'end' => collect($ends)->sortByDesc(fn (Carbon $value) => $value->getTimestamp())->first() ?? self::todayEndUtc(self::DEFAULT_TIMEZONE),
        ];
    }

    /**
     * @param  Collection<int, Hub>  $hubs
     * @return array{start: Carbon, end: Carbon}
     */
    public static function encompassingDateRange(Collection $hubs, string $dateFrom, string $dateTo): array
    {
        $bounds = $hubs->map(function (Hub $hub) use ($dateFrom, $dateTo): array {
            $timezone = self::forHub($hub);

            return [
                'start' => self::startOfDayUtc($dateFrom, $timezone),
                'end' => self::endOfDayUtc($dateTo, $timezone),
            ];
        });

        return [
            'start' => $bounds->sortBy(fn (array $item) => $item['start']->getTimestamp())->first()['start']
                ?? self::startOfDayUtc($dateFrom, self::DEFAULT_TIMEZONE),
            'end' => $bounds->sortByDesc(fn (array $item) => $item['end']->getTimestamp())->first()['end']
                ?? self::endOfDayUtc($dateTo, self::DEFAULT_TIMEZONE),
        ];
    }
}
