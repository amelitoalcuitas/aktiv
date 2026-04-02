<?php

namespace Tests\Feature;

use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubUsernameTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_hub_with_username(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->postJson('/api/hubs', [
                'name' => 'Sunnyvale Tennis Club',
                'username' => 'sunnyvale-tennis',
                'city' => 'Manila',
                'address' => '123 Main St',
                'zip_code' => '1000',
                'province' => 'Metro Manila',
                'country' => 'Philippines',
            ])
            ->assertCreated()
            ->assertJsonPath('data.username', 'sunnyvale-tennis')
            ->assertJsonPath('data.username_changed_at', null);
    }

    public function test_hub_creation_requires_unique_username(): void
    {
        $owner = User::factory()->owner()->create();
        Hub::factory()->create(['username' => 'taken-hub']);

        $this->actingAs($owner)
            ->postJson('/api/hubs', [
                'name' => 'Another Hub',
                'username' => 'taken-hub',
                'city' => 'Manila',
                'address' => '123 Main St',
                'zip_code' => '1000',
                'province' => 'Metro Manila',
                'country' => 'Philippines',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_public_hub_fetch_by_username_succeeds(): void
    {
        $hub = Hub::factory()->create([
            'username' => 'prime-smash',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $this->getJson('/api/hubs/prime-smash')
            ->assertOk()
            ->assertJsonPath('data.id', $hub->id)
            ->assertJsonPath('data.username', 'prime-smash');
    }

    public function test_legacy_uuid_public_hub_fetch_still_succeeds(): void
    {
        $hub = Hub::factory()->create([
            'username' => 'prime-smash',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $this->getJson("/api/hubs/{$hub->id}")
            ->assertOk()
            ->assertJsonPath('data.username', 'prime-smash');
    }

    public function test_owner_can_change_hub_username_on_first_manual_change(): void
    {
        $owner = User::factory()->owner()->create();
        $hub = Hub::factory()->for($owner, 'owner')->create([
            'username' => 'old-handle',
            'username_changed_at' => null,
        ]);

        $this->actingAs($owner)
            ->putJson("/api/hubs/{$hub->id}", ['username' => 'new-handle'])
            ->assertOk()
            ->assertJsonPath('data.username', 'new-handle');
    }

    public function test_hub_username_change_is_rate_limited_to_once_per_month(): void
    {
        $owner = User::factory()->owner()->create();
        $hub = Hub::factory()->for($owner, 'owner')->create([
            'username' => 'old-handle',
            'username_changed_at' => now()->subWeeks(2),
        ]);

        $this->actingAs($owner)
            ->putJson("/api/hubs/{$hub->id}", ['username' => 'new-handle'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_hub_username_not_rate_limited_when_unchanged(): void
    {
        $owner = User::factory()->owner()->create();
        $hub = Hub::factory()->for($owner, 'owner')->create([
            'username' => 'same-handle',
            'username_changed_at' => now()->subDays(1),
        ]);

        $this->actingAs($owner)
            ->putJson("/api/hubs/{$hub->id}", [
                'username' => 'same-handle',
                'description' => 'Updated description',
            ])
            ->assertOk()
            ->assertJsonPath('data.username', 'same-handle');
    }

    public function test_hub_username_availability_endpoint_reports_available_username(): void
    {
        $this->getJson('/api/hubs/username-availability?username=Sunnyvale Tennis')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.username', 'sunnyvale-tennis');
    }

    public function test_hub_username_availability_endpoint_rejects_taken_username(): void
    {
        Hub::factory()->create(['username' => 'taken-hub']);

        $this->getJson('/api/hubs/username-availability?username=taken-hub')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }
}
