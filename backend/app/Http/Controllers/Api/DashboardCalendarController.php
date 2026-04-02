<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\IndexDashboardCalendarRequest;
use App\Http\Resources\DashboardCalendarItemResource;
use App\Models\HubEvent;
use App\Models\OpenPlaySession;
use App\Support\HubTimezone;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class DashboardCalendarController extends Controller
{
    public function index(IndexDashboardCalendarRequest $request): JsonResponse
    {
        $hubs = $request->user()
            ->hubs()
            ->select('id', 'name', 'timezone')
            ->get();

        $hubIds = $hubs->pluck('id');
        $hubNames = $hubs->pluck('name', 'id');
        $hubTimezones = $hubs->mapWithKeys(fn ($hub): array => [$hub->id => $hub->timezone_name]);

        if ($hubIds->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();
        $encompassingRange = HubTimezone::encompassingDateRange($hubs, $dateFrom, $dateTo);
        $rangeStart = $encompassingRange['start'];
        $rangeEnd = $encompassingRange['end'];

        $events = HubEvent::query()
            ->whereIn('hub_id', $hubIds)
            ->where('is_active', true)
            ->where('date_to', '>=', $dateFrom)
            ->where('date_from', '<=', $dateTo)
            ->get();

        $sessions = OpenPlaySession::query()
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->whereHas('booking', fn ($query) => $query
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->where('end_time', '>=', $rangeStart->copy()->utc())
                ->where('start_time', '<=', $rangeEnd->copy()->utc())
                ->whereHas('court', fn ($courtQuery) => $courtQuery
                    ->whereHas('hub', fn ($hubQuery) => $hubQuery->whereIn('id', $hubIds))))
            ->with(['booking.court.hub'])
            ->get();

        $items = $this->normalizeEventItems($events, $hubNames, $hubTimezones, $dateFrom, $dateTo)
            ->concat($this->normalizeOpenPlayItems($sessions, $hubTimezones, $dateFrom, $dateTo))
            ->sortBy([
                ['date', 'asc'],
                ['kind', 'asc'],
                ['time_label', 'asc'],
                ['title', 'asc'],
            ])
            ->values();

        return response()->json([
            'data' => DashboardCalendarItemResource::collection($items),
        ]);
    }

    private function normalizeEventItems(
        Collection $events,
        Collection $hubNames,
        Collection $hubTimezones,
        string $dateFrom,
        string $dateTo,
    ): Collection {
        return $events->flatMap(function (HubEvent $event) use ($hubNames, $hubTimezones, $dateFrom, $dateTo): array {
            $startDate = Carbon::createFromFormat(
                'Y-m-d',
                max($event->date_from->toDateString(), $dateFrom),
                $hubTimezones->get($event->hub_id, HubTimezone::DEFAULT_TIMEZONE)
            )->startOfDay();
            $endDate = Carbon::createFromFormat(
                'Y-m-d',
                min($event->date_to->toDateString(), $dateTo),
                $hubTimezones->get($event->hub_id, HubTimezone::DEFAULT_TIMEZONE)
            )->startOfDay();
            $dates = [];

            for ($cursor = $startDate->copy(); $cursor->lte($endDate); $cursor->addDay()) {
                $dates[] = [
                    'id' => "event:{$event->id}:{$cursor->toDateString()}",
                    'kind' => 'event',
                    'hub_id' => $event->hub_id,
                    'hub_name' => $hubNames->get($event->hub_id, ''),
                    'hub_timezone' => $hubTimezones->get($event->hub_id, HubTimezone::DEFAULT_TIMEZONE),
                    'title' => $this->eventTitle($event),
                    'date' => $cursor->toDateString(),
                    'time_label' => $this->formatEventTimeRange(
                        $event->time_from,
                        $event->time_to,
                        $hubTimezones->get($event->hub_id, HubTimezone::DEFAULT_TIMEZONE)
                    ),
                    'to' => "/dashboard/hubs/{$event->hub_id}/events",
                ];
            }

            return $dates;
        });
    }

    private function normalizeOpenPlayItems(Collection $sessions, Collection $hubTimezones, string $dateFrom, string $dateTo): Collection
    {
        return $sessions->map(function (OpenPlaySession $session) use ($hubTimezones): array {
            $booking = $session->booking;
            $hub = $booking?->court?->hub;
            $timezone = $hubTimezones->get($hub?->id, HubTimezone::DEFAULT_TIMEZONE);

            return [
                'id' => "open-play:{$session->id}",
                'kind' => 'open_play',
                'hub_id' => $hub?->id,
                'hub_name' => $hub?->name ?? '',
                'hub_timezone' => $timezone,
                'title' => $session->title,
                'date' => HubTimezone::localDate($booking->start_time, $timezone),
                'time_label' => $this->formatTimeRange($booking->start_time, $booking->end_time, $timezone),
                'to' => "/dashboard/hubs/{$hub?->id}/open-play",
            ];
        })->filter(fn (array $item): bool => $item['date'] >= $dateFrom && $item['date'] <= $dateTo)->values();
    }

    private function eventTitle(HubEvent $event): string
    {
        if ($event->title) {
            return $event->title;
        }

        if ($event->event_type === 'voucher' && $event->voucher_code) {
            return "Voucher {$event->voucher_code}";
        }

        return 'Untitled event';
    }

    private function formatEventTimeRange(?string $timeFrom, ?string $timeTo, string $timezone): ?string
    {
        if (! $timeFrom) {
            return null;
        }

        $start = $this->formatClockTime($timeFrom, $timezone);
        $end = $timeTo ? $this->formatClockTime($timeTo, $timezone) : null;

        return $end ? "{$start}-{$end}" : $start;
    }

    private function formatClockTime(string $value, string $timezone): string
    {
        foreach (['H:i:s', 'H:i'] as $format) {
            $time = Carbon::createFromFormat($format, $value, $timezone);

            if ($time !== false) {
                return $time->format('g:i A');
            }
        }

        return $value;
    }

    private function formatTimeRange(CarbonInterface $start, CarbonInterface $end, string $timezone): string
    {
        return sprintf(
            '%s-%s',
            $start->clone()->timezone($timezone)->format('g:i A'),
            $end->clone()->timezone($timezone)->format('g:i A'),
        );
    }
}
