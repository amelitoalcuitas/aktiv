<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\IndexDashboardCalendarRequest;
use App\Http\Resources\DashboardCalendarItemResource;
use App\Models\HubEvent;
use App\Models\OpenPlaySession;
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
            ->select('id', 'name')
            ->get();

        $hubIds = $hubs->pluck('id');
        $hubNames = $hubs->pluck('name', 'id');

        if ($hubIds->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $rangeStart = Carbon::createFromFormat('Y-m-d', $request->string('date_from')->toString(), 'Asia/Manila')
            ->startOfDay();
        $rangeEnd = Carbon::createFromFormat('Y-m-d', $request->string('date_to')->toString(), 'Asia/Manila')
            ->endOfDay();

        $events = HubEvent::query()
            ->whereIn('hub_id', $hubIds)
            ->where('is_active', true)
            ->where('date_to', '>=', $rangeStart->toDateString())
            ->where('date_from', '<=', $rangeEnd->toDateString())
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

        $items = $this->normalizeEventItems($events, $hubNames, $rangeStart, $rangeEnd)
            ->concat($this->normalizeOpenPlayItems($sessions))
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
        CarbonInterface $rangeStart,
        CarbonInterface $rangeEnd,
    ): Collection {
        return $events->flatMap(function (HubEvent $event) use ($hubNames, $rangeStart, $rangeEnd): array {
            $startDate = $event->date_from->copy()->max($rangeStart);
            $endDate = $event->date_to->copy()->min($rangeEnd);
            $dates = [];

            for ($cursor = $startDate->copy(); $cursor->lte($endDate); $cursor->addDay()) {
                $dates[] = [
                    'id' => "event:{$event->id}:{$cursor->toDateString()}",
                    'kind' => 'event',
                    'hub_id' => $event->hub_id,
                    'hub_name' => $hubNames->get($event->hub_id, ''),
                    'title' => $this->eventTitle($event),
                    'date' => $cursor->toDateString(),
                    'time_label' => $this->formatEventTimeRange($event->time_from, $event->time_to),
                    'to' => "/dashboard/hubs/{$event->hub_id}/events",
                ];
            }

            return $dates;
        });
    }

    private function normalizeOpenPlayItems(Collection $sessions): Collection
    {
        return $sessions->map(function (OpenPlaySession $session): array {
            $booking = $session->booking;
            $hub = $booking?->court?->hub;

            return [
                'id' => "open-play:{$session->id}",
                'kind' => 'open_play',
                'hub_id' => $hub?->id,
                'hub_name' => $hub?->name ?? '',
                'title' => $session->title,
                'date' => $booking->start_time->clone()->timezone('Asia/Manila')->toDateString(),
                'time_label' => $this->formatTimeRange($booking->start_time, $booking->end_time),
                'to' => "/dashboard/hubs/{$hub?->id}/open-play",
            ];
        });
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

    private function formatEventTimeRange(?string $timeFrom, ?string $timeTo): ?string
    {
        if (! $timeFrom) {
            return null;
        }

        $start = $this->formatClockTime($timeFrom);
        $end = $timeTo ? $this->formatClockTime($timeTo) : null;

        return $end ? "{$start}-{$end}" : $start;
    }

    private function formatClockTime(string $value): string
    {
        foreach (['H:i:s', 'H:i'] as $format) {
            $time = Carbon::createFromFormat($format, $value, 'Asia/Manila');

            if ($time !== false) {
                return $time->format('g:i A');
            }
        }

        return $value;
    }

    private function formatTimeRange(CarbonInterface $start, CarbonInterface $end): string
    {
        return sprintf(
            '%s-%s',
            $start->clone()->timezone('Asia/Manila')->format('g:i A'),
            $end->clone()->timezone('Asia/Manila')->format('g:i A'),
        );
    }
}
