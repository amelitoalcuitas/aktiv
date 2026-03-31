<?php

namespace Tests\Feature;

use App\Models\Hub;
use App\Models\HubSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubSettingsTest extends TestCase
{
    use RefreshDatabase;


    public function test_require_account_to_book_defaults_to_true_on_new_hub(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->postJson('/api/hubs', [
            'name'     => 'Test Hub',
            'city'     => 'Manila',
            'address'  => '123 Main St',
            'zip_code' => '1000',
            'province' => 'Metro Manila',
            'country'  => 'Philippines',
        ]);

        $response->assertCreated();
        $hub = Hub::where('name', 'Test Hub')->firstOrFail();
        $this->assertDatabaseHas('hub_settings', [
            'hub_id'                  => $hub->id,
            'require_account_to_book' => true,
        ]);
    }


    public function test_hub_owner_can_toggle_require_account_to_book_off(): void
    {
        $owner = User::factory()->owner()->create();
        $hub = Hub::factory()->create(['owner_id' => $owner->id]);
        HubSettings::factory()->create([
            'hub_id'                  => $hub->id,
            'require_account_to_book' => true,
        ]);

        $response = $this->actingAs($owner)->postJson("/api/hubs/{$hub->id}", [
            '_method'                 => 'PUT',
            'require_account_to_book' => false,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('hub_settings', [
            'hub_id'                  => $hub->id,
            'require_account_to_book' => false,
        ]);
    }


    public function test_hub_setting_is_returned_in_api_response(): void
    {
        $hub = Hub::factory()->create([
            'is_approved' => true,
            'is_active'   => true,
        ]);
        HubSettings::factory()->create([
            'hub_id'                  => $hub->id,
            'require_account_to_book' => false,
        ]);

        $response = $this->getJson("/api/hubs/{$hub->id}");

        $response->assertOk()->assertJsonPath('data.require_account_to_book', false);
    }
}
