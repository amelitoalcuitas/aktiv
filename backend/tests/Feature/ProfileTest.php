<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserHeart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_own_profile(): void
    {
        $user = User::factory()->create(['bio' => 'Test bio']);

        $this->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.bio', 'Test bio')
            ->assertJsonStructure(['data' => [
                'id', 'name', 'email', 'avatar_url', 'avatar_thumb_url', 'banner_url',
                'bio', 'social_links', 'profile_privacy', 'hearts_count',
                'is_hub_owner', 'created_at',
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
                'name'         => 'New Name',
                'bio'          => 'My bio',
                'phone'        => '+63 912 345 6789',
                'social_links' => ['facebook' => 'fb.com/test', 'instagram' => 'ig.com/test'],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.bio', 'My bio');

        $this->assertDatabaseHas('users', [
            'id'   => $user->id,
            'name' => 'New Name',
            'bio'  => 'My bio',
        ]);
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
            ->assertJsonMissing(['email', 'phone']);
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
}
