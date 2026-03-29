<?php

namespace App\Services;

use App\Models\Court;
use App\Models\Hub;
use App\Models\HubEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HubEventDiscountService
{
    public function normalizeVoucherCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = strtoupper(trim($code));

        return $normalized === '' ? null : $normalized;
    }

    public function resolveBookingPricing(
        Hub $hub,
        Court $court,
        Carbon $startTime,
        Carbon $endTime,
        ?string $voucherCode = null,
        ?User $user = null,
        ?string $guestEmail = null,
        bool $lockVoucher = false,
        bool $allowMissingIdentityForPerUserPreview = false
    ): array {
        $hours = $startTime->diffInMinutes($endTime) / 60;
        $pricePerHour = (float) $court->price_per_hour;
        $totalPrice = $pricePerHour > 0 ? round($pricePerHour * $hours, 2) : null;

        if ($totalPrice === null) {
            return [
                'total_price' => null,
                'original_price' => null,
                'discount_amount' => null,
                'applied_discount' => null,
            ];
        }

        $normalizedVoucherCode = $this->normalizeVoucherCode($voucherCode);
        if ($normalizedVoucherCode !== null) {
            $voucherEvent = $this->findVoucherEvent(
                $hub,
                $court,
                $startTime,
                $endTime,
                $normalizedVoucherCode,
                $lockVoucher
            );

            if (! $voucherEvent) {
                throw ValidationException::withMessages([
                    'voucher_code' => 'This voucher code is invalid for the selected booking.',
                ]);
            }

            $this->assertVoucherUsesAvailable(
                event: $voucherEvent,
                user: $user,
                guestEmail: $guestEmail,
                allowMissingIdentityForPerUserPreview: $allowMissingIdentityForPerUserPreview,
            );

            $discount = $voucherEvent->discountForCourt($court->id);
            $discountedPrice = $this->applyDiscount($totalPrice, $discount);

            return [
                'total_price' => $discountedPrice,
                'original_price' => $totalPrice,
                'discount_amount' => round($totalPrice - $discountedPrice, 2),
                'applied_discount' => [
                    'source' => 'voucher',
                    'title' => $voucherEvent->title,
                    'label' => $voucherEvent->title ?: $voucherEvent->voucher_code,
                    'code' => $voucherEvent->voucher_code,
                    'event_id' => $voucherEvent->id,
                    'discount_type' => $discount['discount_type'],
                    'discount_value' => $discount['discount_value'],
                    'overrides_promo' => true,
                ],
            ];
        }

        $promoEvent = $this->findPromoEvent($hub, $court, $startTime, $endTime);
        if (! $promoEvent) {
            return [
                'total_price' => $totalPrice,
                'original_price' => null,
                'discount_amount' => null,
                'applied_discount' => null,
            ];
        }

        $discount = $promoEvent->discountForCourt($court->id);
        $discountedPrice = $this->applyDiscount($totalPrice, $discount);

        return [
            'total_price' => $discountedPrice,
            'original_price' => $totalPrice,
            'discount_amount' => round($totalPrice - $discountedPrice, 2),
            'applied_discount' => [
                'source' => 'promo',
                'title' => $promoEvent->title,
                'label' => $promoEvent->title,
                'code' => null,
                'event_id' => $promoEvent->id,
                'discount_type' => $discount['discount_type'],
                'discount_value' => $discount['discount_value'],
                'overrides_promo' => false,
            ],
        ];
    }

    public function previewVoucher(
        Hub $hub,
        array $items,
        string $voucherCode,
        ?User $user = null,
        ?string $guestEmail = null
    ): array
    {
        $normalizedVoucherCode = $this->normalizeVoucherCode($voucherCode);

        if ($normalizedVoucherCode === null) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Enter a voucher code.',
            ]);
        }

        $courts = Court::query()
            ->where('hub_id', $hub->id)
            ->whereIn('id', collect($items)->pluck('court_id')->all())
            ->get()
            ->keyBy('id');

        $lineItems = [];
        $totalOriginal = 0.0;
        $totalDiscounted = 0.0;
        $totalDiscount = 0.0;
        $appliedDiscount = null;

        foreach ($items as $item) {
            $court = $courts->get($item['court_id']);
            if (! $court) {
                throw ValidationException::withMessages([
                    'voucher_code' => 'One or more selected courts are invalid for this hub.',
                ]);
            }

            $startTime = Carbon::parse($item['start_time']);
            $endTime = Carbon::parse($item['end_time']);

            $pricing = $this->resolveBookingPricing(
                hub: $hub,
                court: $court,
                startTime: $startTime,
                endTime: $endTime,
                voucherCode: $normalizedVoucherCode,
                user: $user,
                guestEmail: $guestEmail,
                allowMissingIdentityForPerUserPreview: $user === null && blank($guestEmail),
            );

            $original = (float) ($pricing['original_price'] ?? 0);
            $discounted = (float) ($pricing['total_price'] ?? 0);
            $discountAmount = (float) ($pricing['discount_amount'] ?? 0);

            $totalOriginal += $original;
            $totalDiscounted += $discounted;
            $totalDiscount += $discountAmount;
            $appliedDiscount ??= $pricing['applied_discount'];

            $lineItems[] = [
                'court_id' => $court->id,
                'start_time' => $startTime->toIso8601String(),
                'end_time' => $endTime->toIso8601String(),
                'original_price' => round($original, 2),
                'discounted_price' => round($discounted, 2),
                'discount_amount' => round($discountAmount, 2),
            ];
        }

        return [
            'voucher_code' => $normalizedVoucherCode,
            'summary' => [
                'original_total' => round($totalOriginal, 2),
                'discounted_total' => round($totalDiscounted, 2),
                'discount_amount' => round($totalDiscount, 2),
            ],
            'applied_discount' => $appliedDiscount,
            'items' => $lineItems,
        ];
    }

    public function findClosureEvent(Hub $hub, Court $court, Carbon $startTime, Carbon $endTime): ?HubEvent
    {
        return $this->findEventForWindow($hub, $court, $startTime, $endTime, 'closure');
    }

    public function applyDiscount(float $price, array $discount): float
    {
        $value = $discount['discount_value'];

        if ($discount['discount_type'] === 'percent') {
            $discounted = $price * (1 - min($value, 100) / 100);
        } else {
            $discounted = max(0, $price - $value);
        }

        return round($discounted, 2);
    }

    private function findPromoEvent(Hub $hub, Court $court, Carbon $startTime, Carbon $endTime): ?HubEvent
    {
        return HubEvent::where('hub_id', $hub->id)
            ->where('event_type', 'promo')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNotNull('discount_type')->orWhereNotNull('court_discounts'))
            ->get()
            ->first(fn (HubEvent $event) => $this->matchesEventWindow($event, $court, $startTime, $endTime));
    }

    private function findVoucherEvent(
        Hub $hub,
        Court $court,
        Carbon $startTime,
        Carbon $endTime,
        string $voucherCode,
        bool $lockVoucher = false
    ): ?HubEvent {
        $query = HubEvent::where('hub_id', $hub->id)
            ->where('event_type', 'voucher')
            ->where('voucher_code', $voucherCode)
            ->where('is_active', true);

        if ($lockVoucher) {
            $query->lockForUpdate();
        }

        return $query->get()
            ->first(fn (HubEvent $event) => $this->matchesEventWindow($event, $court, $startTime, $endTime));
    }

    private function findEventForWindow(
        Hub $hub,
        Court $court,
        Carbon $startTime,
        Carbon $endTime,
        string $eventType
    ): ?HubEvent {
        return HubEvent::where('hub_id', $hub->id)
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->get()
            ->first(fn (HubEvent $event) => $this->matchesEventWindow($event, $court, $startTime, $endTime));
    }

    private function matchesEventWindow(HubEvent $event, Court $court, Carbon $startTime, Carbon $endTime): bool
    {
        $bookingStart = $startTime->copy()->setTimezone('Asia/Manila')->startOfDay();
        $bookingEnd = $endTime->copy()->setTimezone('Asia/Manila')->endOfDay();

        if ($event->date_from->gt($bookingEnd) || $event->date_to->lt($bookingStart)) {
            return false;
        }

        if ($event->time_from && $event->time_to) {
            $slotStart = $startTime->copy()->setTimezone('Asia/Manila')->format('H:i');
            $slotEnd = $endTime->copy()->setTimezone('Asia/Manila')->format('H:i');

            if (! ($slotStart < substr($event->time_to, 0, 5) && $slotEnd > substr($event->time_from, 0, 5))) {
                return false;
            }
        }

        return $event->appliesToCourt($court->id);
    }

    private function assertVoucherUsesAvailable(
        HubEvent $event,
        ?User $user = null,
        ?string $guestEmail = null,
        bool $allowMissingIdentityForPerUserPreview = false
    ): void {
        if ($event->limit_total_uses) {
            $totalUses = $this->activeVoucherUsageQuery($event)->count();
            if ($totalUses >= (int) $event->max_total_uses) {
                throw ValidationException::withMessages([
                    'voucher_code' => 'This voucher has reached its total usage limit.',
                ]);
            }
        }

        if (! $event->limit_per_user_uses) {
            return;
        }

        $query = $this->activeVoucherUsageQuery($event);

        if ($user !== null) {
            $query->where('booked_by', $user->id);
        } elseif (filled($guestEmail)) {
            $query->where('guest_email', $guestEmail);
        } elseif ($allowMissingIdentityForPerUserPreview) {
            return;
        } else {
            throw ValidationException::withMessages([
                'voucher_code' => 'This voucher needs your account or email before its per-user limit can be checked.',
            ]);
        }

        if ($query->count() >= (int) $event->max_uses_per_user) {
            throw ValidationException::withMessages([
                'voucher_code' => 'You have reached the usage limit for this voucher.',
            ]);
        }
    }

    private function activeVoucherUsageQuery(HubEvent $event)
    {
        return DB::table('bookings')
            ->where('applied_hub_event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
