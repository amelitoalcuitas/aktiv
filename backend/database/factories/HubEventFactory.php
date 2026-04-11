<?php

namespace Database\Factories;

use App\Models\Hub;
use App\Support\HubTimezone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HubEvent>
 */
class HubEventFactory extends Factory
{
    public function definition(): array
    {
        $timezone = HubTimezone::DEFAULT_TIMEZONE;
        $startLocal = Carbon::now($timezone)->startOfDay();
        $endLocal = $startLocal->copy()->addDays(7)->endOfDay();

        return [
            'hub_id'          => Hub::factory(),
            'title'           => fake()->sentence(3),
            'description'     => fake()->sentence(10),
            'event_type'      => 'announcement',
            'start_time'      => $startLocal->copy()->utc(),
            'end_time'        => $endLocal->copy()->utc(),
            'discount_type'   => null,
            'discount_value'  => null,
            'voucher_code'    => null,
            'show_announcement' => true,
            'limit_total_uses' => false,
            'max_total_uses' => null,
            'limit_per_user_uses' => false,
            'max_uses_per_user' => null,
            'affected_courts' => null,
            'is_active'       => true,
        ];
    }

    public function closure(): static
    {
        return $this->state(['event_type' => 'closure', 'discount_type' => null, 'discount_value' => null]);
    }

    public function promo(string $discountType = 'percent', float $discountValue = 20): static
    {
        return $this->state([
            'event_type'     => 'promo',
            'discount_type'  => $discountType,
            'discount_value' => $discountValue,
        ]);
    }

    public function voucher(
        string $discountType = 'percent',
        float $discountValue = 20,
        string $voucherCode = 'SAVE12345678'
    ): static {
        return $this->state([
            'event_type' => 'voucher',
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(10),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'voucher_code' => $voucherCode,
            'show_announcement' => true,
            'limit_total_uses' => false,
            'max_total_uses' => null,
            'limit_per_user_uses' => false,
            'max_uses_per_user' => null,
        ]);
    }
}
