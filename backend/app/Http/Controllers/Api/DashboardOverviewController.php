<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OwnerBookingResource;
use App\Models\Booking;
use App\Models\Hub;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardOverviewController extends Controller
{
    private const ACTIONABLE_STATUSES = ['payment_sent', 'pending_payment'];

    private const ACTION_NEEDED_LIMIT = 10;

    private const TODAY_SCHEDULE_LIMIT = 10;

    public function index(Request $request): JsonResponse
    {
        $hubs = Hub::query()
            ->where('owner_id', $request->user()->id)
            ->select('id', 'name', 'is_active')
            ->orderByDesc('created_at')
            ->get();

        if ($hubs->isEmpty()) {
            return response()->json([
                'data' => [
                    'summary' => [
                        'needs_review_count' => 0,
                        'pending_payments_count' => 0,
                        'today_confirmed_count' => 0,
                        'revenue_today' => 0,
                    ],
                    'hubs' => [],
                    'action_needed' => [],
                    'today_schedule' => [],
                ],
            ]);
        }

        $todayStart = now('Asia/Manila')->startOfDay()->utc();
        $todayEnd = now('Asia/Manila')->endOfDay()->utc();

        $hubIds = $hubs->pluck('id');
        $hubNames = $hubs->pluck('name', 'id');

        $bookings = Booking::query()
            ->whereHas('court', fn ($query) => $query->whereIn('hub_id', $hubIds))
            ->where(function ($query) use ($todayStart, $todayEnd): void {
                $query->whereIn('status', self::ACTIONABLE_STATUSES)
                    ->orWhere(function ($todayQuery) use ($todayStart, $todayEnd): void {
                        $todayQuery->where('end_time', '>=', $todayStart)
                            ->where('start_time', '<=', $todayEnd);
                    });
            })
            ->with([
                'court:id,name,hub_id',
                'bookedBy:id,first_name,last_name,email,contact_number,avatar_url',
            ])
            ->orderByDesc('created_at')
            ->get();

        $actionNeeded = $bookings
            ->filter(fn (Booking $booking): bool => in_array($booking->status, self::ACTIONABLE_STATUSES, true))
            ->sortByDesc(fn (Booking $booking): Carbon => $booking->created_at)
            ->values();

        $todaySchedule = $bookings
            ->filter(fn (Booking $booking): bool => $booking->end_time->gte($todayStart) && $booking->start_time->lte($todayEnd))
            ->sortBy(fn (Booking $booking): Carbon => $booking->start_time)
            ->values();

        $summary = [
            'needs_review_count' => $actionNeeded->where('status', 'payment_sent')->count(),
            'pending_payments_count' => $actionNeeded->where('status', 'pending_payment')->count(),
            'today_confirmed_count' => $todaySchedule->where('status', 'confirmed')->count(),
            'revenue_today' => round(
                $todaySchedule
                    ->where('status', 'confirmed')
                    ->sum(fn (Booking $booking): float => (float) ($booking->total_price ?? 0)),
                2,
            ),
        ];

        $hubBreakdown = $hubs->map(function (Hub $hub) use ($bookings, $todayStart, $todayEnd): array {
            $hubBookings = $bookings->filter(fn (Booking $booking): bool => (string) $booking->court?->hub_id === (string) $hub->id)->values();
            $hubActionNeeded = $hubBookings->filter(
                fn (Booking $booking): bool => in_array($booking->status, self::ACTIONABLE_STATUSES, true)
            );
            $hubToday = $hubBookings->filter(
                fn (Booking $booking): bool => $booking->end_time->gte($todayStart)
                    && $booking->start_time->lte($todayEnd)
            );

            return [
                'hub_id' => $hub->id,
                'hub_name' => $hub->name,
                'is_active' => $hub->is_active,
                'needs_review_count' => $hubActionNeeded->where('status', 'payment_sent')->count(),
                'pending_payments_count' => $hubActionNeeded->where('status', 'pending_payment')->count(),
                'today_confirmed_count' => $hubToday->where('status', 'confirmed')->count(),
                'revenue_today' => round(
                    $hubToday
                        ->where('status', 'confirmed')
                        ->sum(fn (Booking $booking): float => (float) ($booking->total_price ?? 0)),
                    2,
                ),
            ];
        })->all();

        return response()->json([
            'data' => [
                'summary' => $summary,
                'hubs' => $hubBreakdown,
                'action_needed' => OwnerBookingResource::collection(
                    $this->attachHubNames($actionNeeded->take(self::ACTION_NEEDED_LIMIT), $hubNames)
                )->resolve(),
                'today_schedule' => OwnerBookingResource::collection(
                    $this->attachHubNames($todaySchedule->take(self::TODAY_SCHEDULE_LIMIT), $hubNames)
                )->resolve(),
            ],
        ]);
    }

    private function attachHubNames(Collection $bookings, Collection $hubNames): Collection
    {
        return $bookings->map(function (Booking $booking) use ($hubNames): Booking {
            $booking->setAttribute('hub_name', $hubNames->get($booking->court?->hub_id, ''));

            return $booking;
        });
    }
}
