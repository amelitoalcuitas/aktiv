<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_cannot_access_dashboard_routes(): void
    {
        $user = User::factory()->create(); // role=user, verified

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/dashboard/hubs')
             ->assertForbidden();
    }

    public function test_owner_role_can_access_dashboard_routes(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/dashboard/hubs')
             ->assertOk();
    }

    public function test_super_admin_role_can_access_dashboard_routes(): void
    {
        $user = User::factory()->owner()->create(['role' => \App\Enums\UserRole::SuperAdmin]);

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/dashboard/hubs')
             ->assertOk();
    }

    public function test_unverified_owner_cannot_access_dashboard_routes(): void
    {
        $user = User::factory()->owner()->unverified()->create();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/dashboard/hubs')
             ->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/dashboard/hubs')
             ->assertUnauthorized();
    }
}
