<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SuperAdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_user_with_required_location(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
        ]);

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/panel/users', [
                'first_name' => 'Jamie',
                'last_name' => 'Rivera',
                'email' => 'jamie@example.com',
                'country' => 'Philippines',
                'province' => 'Metro Manila',
                'city' => 'Pasig',
                'role' => 'user',
            ])
            ->assertCreated()
            ->assertJsonPath('country', 'Philippines')
            ->assertJsonPath('province', 'Metro Manila')
            ->assertJsonPath('city', 'Pasig');

        $createdUser = User::query()->where('email', 'jamie@example.com')->firstOrFail();

        $this->assertSame('Philippines', $createdUser->country);
        $this->assertSame('Metro Manila', $createdUser->province);
        $this->assertSame('Pasig', $createdUser->city);

        Notification::assertSentTo($createdUser, AccountCreatedNotification::class);
    }

    public function test_super_admin_user_creation_requires_location_fields(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
        ]);

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/panel/users', [
                'first_name' => 'Jamie',
                'last_name' => 'Rivera',
                'email' => 'jamie@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'country',
                'province',
                'city',
            ]);
    }

    public function test_super_admin_can_update_user_profile_fields_and_role(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::User,
            'contact_number' => null,
            'country' => 'Philippines',
            'province' => 'Laguna',
            'city' => 'Calamba',
        ]);

        $this->actingAs($superAdmin, 'sanctum')
            ->putJson("/api/panel/users/{$user->id}", [
                'first_name' => 'Jamie',
                'last_name' => 'Rivera',
                'email' => $user->email,
                'contact_number' => '+63 999 888 7777',
                'country' => 'Philippines',
                'province' => 'Metro Manila',
                'city' => 'Pasig',
                'role' => 'owner',
            ])
            ->assertOk()
            ->assertJsonPath('first_name', 'Jamie')
            ->assertJsonPath('last_name', 'Rivera')
            ->assertJsonPath('contact_number', '+63 999 888 7777')
            ->assertJsonPath('province', 'Metro Manila')
            ->assertJsonPath('city', 'Pasig')
            ->assertJsonPath('role', 'owner');

        $user->refresh();

        $this->assertSame('Jamie', $user->first_name);
        $this->assertSame('Rivera', $user->last_name);
        $this->assertSame('+63 999 888 7777', $user->contact_number);
        $this->assertSame('Metro Manila', $user->province);
        $this->assertSame('Pasig', $user->city);
        $this->assertSame(UserRole::Owner, $user->role);
    }

    public function test_super_admin_user_update_requires_location_fields(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
        ]);

        $user = User::factory()->create();

        $this->actingAs($superAdmin, 'sanctum')
            ->putJson("/api/panel/users/{$user->id}", [
                'first_name' => 'Jamie',
                'last_name' => 'Rivera',
                'email' => $user->email,
                'role' => 'user',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'country',
                'province',
                'city',
            ]);
    }

    public function test_super_admin_user_update_rejects_duplicate_email(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
        ]);

        $user = User::factory()->create();
        $otherUser = User::factory()->create([
            'email' => 'taken@example.com',
        ]);

        $this->actingAs($superAdmin, 'sanctum')
            ->putJson("/api/panel/users/{$user->id}", [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $otherUser->email,
                'contact_number' => $user->contact_number,
                'country' => $user->country,
                'province' => $user->province,
                'city' => $user->city,
                'role' => $user->role->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_super_admin_email_change_resets_verification_and_sends_verification_notification(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
        ]);

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($superAdmin, 'sanctum')
            ->putJson("/api/panel/users/{$user->id}", [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => 'new@example.com',
                'contact_number' => $user->contact_number,
                'country' => $user->country,
                'province' => $user->province,
                'city' => $user->city,
                'role' => $user->role->value,
            ])
            ->assertOk()
            ->assertJsonPath('email', 'new@example.com')
            ->assertJsonPath('email_verified', false);

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}
