<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserHeart;
use App\Notifications\ChangePasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_own_profile(): void
    {
        $user = User::factory()->create([
            'bio' => 'Test bio',
            'is_premium' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.bio', 'Test bio')
            ->assertJsonPath('data.is_premium', true)
            ->assertJsonStructure(['data' => [
                'id', 'first_name', 'last_name', 'username', 'email',
                'avatar_url', 'avatar_thumb_url', 'banner_url',
                'bio', 'social_links', 'profile_privacy', 'hearts_count', 'is_premium',
                'is_hub_owner', 'created_at', 'username_changed_at', 'name_changed_at',
            ]]);
    }

    public function test_guest_cannot_fetch_profile(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'first_name'     => 'New',
                'last_name'      => 'Name',
                'bio'            => 'My bio',
                'contact_number' => '+63 912 345 6789',
                'country'        => 'Philippines',
                'province'       => 'Zamboanga del Sur',
                'city'           => 'Pagadian',
                'social_links'   => ['facebook' => 'fb.com/test', 'instagram' => 'ig.com/test'],
            ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'New')
            ->assertJsonPath('data.last_name', 'Name')
            ->assertJsonPath('data.bio', 'My bio')
            ->assertJsonPath('data.country', 'Philippines')
            ->assertJsonPath('data.province', 'Zamboanga del Sur')
            ->assertJsonPath('data.city', 'Pagadian');

        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'first_name' => 'New',
            'last_name'  => 'Name',
            'bio'        => 'My bio',
            'country'    => 'Philippines',
            'province'   => 'Zamboanga del Sur',
            'city'       => 'Pagadian',
        ]);
    }

    public function test_user_can_update_username(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/profile', ['username' => 'mynewhandle'])
            ->assertOk()
            ->assertJsonPath('data.username', 'mynewhandle');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => 'mynewhandle']);
    }

    public function test_username_must_be_unique(): void
    {
        $existing = User::factory()->create(['username' => 'takenhandle']);
        $user     = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/profile', ['username' => 'takenhandle'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_name_change_is_rate_limited_to_once_per_three_months(): void
    {
        $user = User::factory()->create([
            'name_changed_at' => now()->subMonths(2),
        ]);

        $this->actingAs($user)
            ->putJson('/api/profile', ['first_name' => 'Blocked'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name']);
    }

    public function test_name_change_is_allowed_after_three_months(): void
    {
        $user = User::factory()->create([
            'name_changed_at' => now()->subMonths(4),
        ]);

        $this->actingAs($user)
            ->putJson('/api/profile', ['first_name' => 'Allowed'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Allowed');
    }

    public function test_name_change_is_allowed_on_first_change(): void
    {
        $user = User::factory()->create(['name_changed_at' => null]);

        $this->actingAs($user)
            ->putJson('/api/profile', ['first_name' => 'FirstChange'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'FirstChange');
    }

    public function test_username_change_is_rate_limited_to_once_per_month(): void
    {
        $user = User::factory()->create([
            'username'            => 'oldhandle',
            'username_changed_at' => now()->subWeeks(2),
        ]);

        $this->actingAs($user)
            ->putJson('/api/profile', ['username' => 'newhandle'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_username_change_is_allowed_after_one_month(): void
    {
        $user = User::factory()->create([
            'username'            => 'oldhandle',
            'username_changed_at' => now()->subMonths(2),
        ]);

        $this->actingAs($user)
            ->putJson('/api/profile', ['username' => 'newhandle'])
            ->assertOk()
            ->assertJsonPath('data.username', 'newhandle');
    }

    public function test_username_change_is_allowed_on_first_manual_change(): void
    {
        // username_changed_at is null when auto-generated at registration
        $user = User::factory()->create([
            'username'            => 'johndoe',
            'username_changed_at' => null,
        ]);

        $this->actingAs($user)
            ->putJson('/api/profile', ['username' => 'myrealhandle'])
            ->assertOk()
            ->assertJsonPath('data.username', 'myrealhandle');
    }

    public function test_username_not_rate_limited_when_unchanged(): void
    {
        $user = User::factory()->create([
            'username'            => 'samehandle',
            'username_changed_at' => now()->subDays(1),
        ]);

        // Sending same username value — not a real change, should not be rate-limited
        $this->actingAs($user)
            ->putJson('/api/profile', ['username' => 'samehandle', 'bio' => 'Updated bio'])
            ->assertOk();
    }

    public function test_user_can_update_privacy_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'profile_privacy' => [
                    'show_visited_hubs' => false,
                    'show_leaderboard'  => false,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.profile_privacy.show_visited_hubs', false)
            ->assertJsonPath('data.profile_privacy.show_leaderboard', false)
            ->assertJsonPath('data.profile_privacy.show_hearts', true);
    }

    public function test_public_user_profile_is_accessible(): void
    {
        $user = User::factory()->create(['bio' => 'Hello there']);

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.bio', 'Hello there')
            ->assertJsonMissing(['email', 'contact_number']);
    }

    public function test_resolve_username_returns_uuid(): void
    {
        $user = User::factory()->create(['username' => 'findme']);

        $this->getJson('/api/users/resolve/findme')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_resolve_username_returns_404_for_unknown(): void
    {
        $this->getJson('/api/users/resolve/doesnotexist')->assertNotFound();
    }

    public function test_registration_generates_username_from_name(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john@example.com',
            'country'               => 'Philippines',
            'province'              => 'Zamboanga del Sur',
            'city'                  => 'Pagadian',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['username' => 'johndoe', 'email' => 'john@example.com']);
    }

    public function test_registration_resolves_username_collision(): void
    {
        User::factory()->create(['username' => 'johndoe']);

        $this->postJson('/api/auth/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'email'                 => 'john2@example.com',
            'country'               => 'Philippines',
            'province'              => 'Zamboanga del Sur',
            'city'                  => 'Pagadian',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['username' => 'johndoe1', 'email' => 'john2@example.com']);
    }

    public function test_authenticated_user_can_heart_another_user(): void
    {
        $sender   = User::factory()->create();
        $receiver = User::factory()->create();

        $this->actingAs($sender)
            ->postJson("/api/users/{$receiver->id}/heart")
            ->assertOk()
            ->assertJsonPath('data.hearted', true)
            ->assertJsonPath('data.hearts_count', 1);

        $this->assertDatabaseHas('user_hearts', [
            'from_user_id' => $sender->id,
            'to_user_id'   => $receiver->id,
        ]);
    }

    public function test_heart_is_toggled_off_on_second_request(): void
    {
        $sender   = User::factory()->create();
        $receiver = User::factory()->create();

        UserHeart::create([
            'from_user_id' => $sender->id,
            'to_user_id'   => $receiver->id,
        ]);

        $this->actingAs($sender)
            ->postJson("/api/users/{$receiver->id}/heart")
            ->assertOk()
            ->assertJsonPath('data.hearted', false)
            ->assertJsonPath('data.hearts_count', 0);
    }

    public function test_user_cannot_heart_themselves(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/users/{$user->id}/heart")
            ->assertUnprocessable();
    }

    public function test_guest_cannot_heart(): void
    {
        $user = User::factory()->create();

        $this->postJson("/api/users/{$user->id}/heart")->assertUnauthorized();
    }

    public function test_avatar_upload_requires_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/profile/avatar', ['avatar' => 'not-a-file'])
            ->assertUnprocessable();
    }

    public function test_banner_upload_requires_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/profile/banner', ['banner' => 'not-a-file'])
            ->assertUnprocessable();
    }

    public function test_avatar_upload_stores_image(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $this->actingAs($user)
            ->postJson('/api/profile/avatar', ['avatar' => $file])
            ->assertOk()
            ->assertJsonStructure(['data' => ['avatar_url', 'avatar_thumb_url']]);

        $user->refresh();
        expect($user->avatar_url)->not->toBeNull();
        expect($user->avatar_thumb_url)->not->toBeNull();
        expect($user->avatar_url)->not->toBe($user->avatar_thumb_url);
    }

    public function test_avatar_upload_generates_two_sizes(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        $this->actingAs($user)
            ->postJson('/api/profile/avatar', ['avatar' => $file])
            ->assertOk();

        $user->refresh();
        expect($user->avatar_url)->toContain('/avatars/');
        expect($user->avatar_thumb_url)->toContain('/avatars/thumbs/');
    }

    public function test_avatar_reupload_generates_new_urls(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/profile/avatar', ['avatar' => UploadedFile::fake()->image('a1.jpg', 200, 200)])
            ->assertOk();

        $user->refresh();
        $firstAvatar = $user->avatar_url;
        $firstThumb  = $user->avatar_thumb_url;

        $this->actingAs($user)
            ->postJson('/api/profile/avatar', ['avatar' => UploadedFile::fake()->image('a2.jpg', 200, 200)])
            ->assertOk();

        $user->refresh();
        expect($user->avatar_url)->not->toBe($firstAvatar);
        expect($user->avatar_thumb_url)->not->toBe($firstThumb);
    }

    public function test_banner_upload_stores_image(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('banner.jpg', 1920, 400);

        $this->actingAs($user)
            ->postJson('/api/profile/banner', ['banner' => $file])
            ->assertOk()
            ->assertJsonStructure(['data' => ['banner_url']]);
    }

    public function test_change_password_sends_email_and_returns_cooldown(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/change-password')
            ->assertOk()
            ->assertJsonPath('cooldown.is_active', true)
            ->assertJsonPath('cooldown.remaining_seconds', 300);

        Notification::assertSentTo($user, ChangePasswordNotification::class);
    }

    public function test_change_password_is_rate_limited_for_five_minutes(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/change-password')
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/change-password')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('cooldown.is_active', true);

        Notification::assertSentToTimes($user, ChangePasswordNotification::class, 1);
    }

    public function test_change_password_status_returns_remaining_cooldown(): void
    {
        $user = User::factory()->create();

        RateLimiter::hit('profile:change-password:' . $user->id, 300);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile/change-password/status')
            ->assertOk()
            ->assertJsonPath('cooldown.is_active', true)
            ->assertJsonPath('cooldown.remaining_seconds', 300);
    }
}
