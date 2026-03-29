<?php

namespace Tests\Feature;

use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_hub_with_platform_aware_links(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/hubs', [
                'name' => 'Center Court Hub',
                'description' => 'A great place to play.',
                'city' => 'Quezon City',
                'address' => '123 Katipunan Ave',
                'zip_code' => '1108',
                'province' => 'Metro Manila',
                'country' => 'Philippines',
                'websites' => [
                    ['platform' => 'facebook', 'url' => 'https://facebook.com/center-court'],
                    ['platform' => 'facebook', 'url' => 'https://facebook.com/center-court-events'],
                    ['platform' => 'instagram', 'url' => 'https://instagram.com/center-court'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.websites.0.platform', 'facebook')
            ->assertJsonPath('data.websites.1.platform', 'facebook')
            ->assertJsonPath('data.websites.2.platform', 'instagram');

        $hub = Hub::query()->firstOrFail();

        $this->assertDatabaseHas('hub_websites', [
            'hub_id' => $hub->id,
            'platform' => 'facebook',
            'url' => 'https://facebook.com/center-court',
        ]);

        $this->assertDatabaseHas('hub_websites', [
            'hub_id' => $hub->id,
            'platform' => 'facebook',
            'url' => 'https://facebook.com/center-court-events',
        ]);
    }

    public function test_admin_can_update_hub_links_with_duplicate_platforms(): void
    {
        $admin = User::factory()->admin()->create();
        $hub = Hub::factory()->for($admin, 'owner')->create([
            'zip_code' => '1108',
            'province' => 'Metro Manila',
            'country' => 'Philippines',
        ]);

        $hub->websites()->createMany([
            ['platform' => 'other', 'url' => 'https://old.example.com'],
        ]);

        $this->actingAs($admin)
            ->putJson("/api/hubs/{$hub->id}", [
                'websites' => [
                    ['platform' => 'threads', 'url' => 'https://threads.net/@centercourt'],
                    ['platform' => 'threads', 'url' => 'https://threads.net/@centercourt-updates'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.websites.0.platform', 'threads')
            ->assertJsonPath('data.websites.1.platform', 'threads');

        $this->assertDatabaseMissing('hub_websites', [
            'hub_id' => $hub->id,
            'url' => 'https://old.example.com',
        ]);

        $this->assertDatabaseHas('hub_websites', [
            'hub_id' => $hub->id,
            'platform' => 'threads',
            'url' => 'https://threads.net/@centercourt',
        ]);

        $this->assertDatabaseHas('hub_websites', [
            'hub_id' => $hub->id,
            'platform' => 'threads',
            'url' => 'https://threads.net/@centercourt-updates',
        ]);
    }

    public function test_hub_link_platform_must_be_supported(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/hubs', [
                'name' => 'Center Court Hub',
                'city' => 'Quezon City',
                'address' => '123 Katipunan Ave',
                'zip_code' => '1108',
                'province' => 'Metro Manila',
                'country' => 'Philippines',
                'websites' => [
                    ['platform' => 'website', 'url' => 'https://example.com'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['websites.0.platform']);
    }
}
