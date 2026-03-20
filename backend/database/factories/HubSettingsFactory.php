<?php

namespace Database\Factories;

use App\Models\Hub;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HubSettings>
 */
class HubSettingsFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hub_id'                  => Hub::factory(),
            'require_account_to_book' => true,
            'payment_methods'         => ['pay_on_site'],
            'payment_qr_url'          => null,
            'digital_bank_name'       => null,
            'digital_bank_account'    => null,
        ];
    }
}
