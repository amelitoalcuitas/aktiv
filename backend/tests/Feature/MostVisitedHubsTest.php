<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MostVisitedHubsTest extends TestCase
{
    use RefreshDatabase;

    // ── Own profile ───────────────────────────────────────────────────────────

    public function test_own_most_visited_hubs_requires_auth(): void
    {
        $this->getJson('/api/user/most-visited-hubs')->assertUnauthorized();
    }

    public function test_own_most_visited_hubs_returns_empty_when_no_bookings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user/most-visited-hubs')
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_own_most_visited_hubs_only_counts_confirmed_and_completed(): void
    {
        $user  = User::factory()->create();
        $court = Court::factory()->create();

        // 2 confirmed bookings at this hub
        Booking::factory()->count(2)->create([
            'booked_by' => $user->id,
            'court_id'  => $court->id,
            'status'    => 'confirmed',
        ]);

        // 1 cancelled — should NOT count
        Booking::factory()->create([
            'booked_by' => $user->id,
            'court_id'  => $court->id,
            'status'    => 'cancelled',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/user/most-visited-hubs')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($court->hub_id, $data[0]['id']);
        $this->assertEquals(2, $data[0]['visit_count']);
    }

    public function test_own_most_visited_hubs_returns_top_3_ordered_by_count(): void
    {
        $user = User::factory()->create();

        // Create 4 hubs with different booking counts (4..1)
        $courts = Court::factory()->count(4)->create();

        foreach ($courts as $index => $court) {
            $count = 4 - $index; // 4, 3, 2, 1
            Booking::factory()->count($count)->create([
                'booked_by' => $user->id,
                'court_id'  => $court->id,
                'status'    => 'confirmed',
            ]);
        }

        $response = $this->actingAs($user)
            ->getJson('/api/user/most-visited-hubs')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals(4, $data[0]['visit_count']);
        $this->assertEquals(3, $data[1]['visit_count']);
        $this->assertEquals(2, $data[2]['visit_count']);
    }

    public function test_own_most_visited_hubs_response_shape(): void
    {
        $user  = User::factory()->create();
        $court = Court::factory()->create();

        Booking::factory()->create([
            'booked_by' => $user->id,
            'court_id'  => $court->id,
            'status'    => 'completed',
        ]);

        $this->actingAs($user)
            ->getJson('/api/user/most-visited-hubs')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'username', 'city', 'cover_image_url', 'visit_count']],
            ]);
    }

    // ── Public profile ────────────────────────────────────────────────────────

    public function test_public_most_visited_hubs_returns_data_when_privacy_allows(): void
    {
        $user  = User::factory()->create(['profile_privacy' => ['show_visited_hubs' => true]]);
        $court = Court::factory()->create();

        Booking::factory()->count(3)->create([
            'booked_by' => $user->id,
            'court_id'  => $court->id,
            'status'    => 'confirmed',
        ]);

        $this->getJson("/api/users/{$user->id}/most-visited-hubs")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_public_most_visited_hubs_returns_403_when_privacy_hidden(): void
    {
        $user = User::factory()->create(['profile_privacy' => ['show_visited_hubs' => false]]);

        $this->getJson("/api/users/{$user->id}/most-visited-hubs")
            ->assertForbidden();
    }

    public function test_public_most_visited_hubs_returns_404_for_deleted_user(): void
    {
        $user = User::factory()->create(['deletion_scheduled_at' => now()->addDays(29)]);

        $this->getJson("/api/users/{$user->id}/most-visited-hubs")
            ->assertNotFound();
    }
}
