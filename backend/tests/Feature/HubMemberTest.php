<?php

use App\Models\Hub;
use App\Models\HubMember;
use App\Models\User;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->hub = Hub::factory()->create(['owner_id' => $this->owner->id, 'is_active' => true]);
    $this->user = User::factory()->create();
});

test('unauthenticated user can view member preview and count', function (): void {
    $this->user->update(['is_premium' => true]);

    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->getJson("/api/hubs/{$this->hub->id}/members")
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total']])
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.is_premium', true);
});

test('unauthenticated user can list members', function (): void {
    $this->user->update(['is_premium' => true]);

    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->getJson("/api/hubs/{$this->hub->id}/members/list")
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_premium', true);
});

test('public member responses fall back to avatar_url when thumb is missing', function (): void {
    $avatarUrl = 'https://example.com/avatar.jpg';

    $this->user->update([
        'avatar_url' => $avatarUrl,
        'avatar_thumb_url' => null,
    ]);

    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->getJson("/api/hubs/{$this->hub->id}/members")
        ->assertOk()
        ->assertJsonPath('data.0.avatar_thumb_url', $avatarUrl);

    $this->getJson("/api/hubs/{$this->hub->id}/members/list")
        ->assertOk()
        ->assertJsonPath('data.0.avatar_thumb_url', $avatarUrl);
});

test('private member responses hide avatars in hub member endpoints', function (): void {
    $rawName = $this->user->name;

    $this->user->update([
        'avatar_url' => 'https://example.com/private-avatar.jpg',
        'avatar_thumb_url' => 'https://example.com/private-avatar-thumb.jpg',
        'profile_privacy' => [
            'profile_visible_to' => 'no_one',
        ],
    ]);

    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->getJson("/api/hubs/{$this->hub->id}/members")
        ->assertOk()
        ->assertJsonPath('data.0.avatar_thumb_url', null)
        ->assertJsonPath('data.0.username', null)
        ->assertJsonPath('data.0.is_private', true);

    expect(data_get(
        $this->getJson("/api/hubs/{$this->hub->id}/members")->json(),
        'data.0.name'
    ))
        ->not->toBe($rawName)
        ->toContain('*');

    $this->getJson("/api/hubs/{$this->hub->id}/members/list")
        ->assertOk()
        ->assertJsonPath('data.0.avatar_thumb_url', null)
        ->assertJsonPath('data.0.username', null)
        ->assertJsonPath('data.0.is_private', true);
});

test('authenticated user can join a hub', function (): void {
    $this->user->update(['is_premium' => true]);

    $this->actingAs($this->user)
        ->postJson("/api/hubs/{$this->hub->id}/members")
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'username', 'avatar_thumb_url', 'is_premium', 'is_private']])
        ->assertJsonPath('data.is_premium', true);

    $this->assertDatabaseHas('hub_members', [
        'hub_id'  => $this->hub->id,
        'user_id' => $this->user->id,
    ]);
});

test('user cannot join the same hub twice', function (): void {
    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->postJson("/api/hubs/{$this->hub->id}/members")
        ->assertStatus(422);
});

test('authenticated user can leave a hub', function (): void {
    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/hubs/{$this->hub->id}/members")
        ->assertNoContent();

    $this->assertDatabaseMissing('hub_members', [
        'hub_id'  => $this->hub->id,
        'user_id' => $this->user->id,
    ]);
});

test('leaving a hub when not a member returns 422', function (): void {
    $this->actingAs($this->user)
        ->deleteJson("/api/hubs/{$this->hub->id}/members")
        ->assertStatus(422);
});

test('unauthenticated user cannot join a hub', function (): void {
    $this->postJson("/api/hubs/{$this->hub->id}/members")
        ->assertUnauthorized();
});

test('hub show response includes member data', function (): void {
    $avatarUrl = 'https://example.com/member-avatar.jpg';

    $this->user->update([
        'is_premium' => true,
        'avatar_url' => $avatarUrl,
        'avatar_thumb_url' => null,
    ]);

    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->getJson("/api/hubs/{$this->hub->id}")
        ->assertOk()
        ->assertJsonStructure(['data' => ['members_count', 'member_preview', 'is_member']])
        ->assertJsonPath('data.members_count', 1)
        ->assertJsonPath('data.is_member', false)
        ->assertJsonPath('data.member_preview.0.avatar_thumb_url', $avatarUrl)
        ->assertJsonPath('data.member_preview.0.is_premium', true)
        ->assertJsonPath('data.member_preview.0.is_private', false);
});

test('hub show member preview hides private member data', function (): void {
    $rawName = $this->user->name;

    $this->user->update([
        'avatar_url' => 'https://example.com/private-avatar.jpg',
        'avatar_thumb_url' => 'https://example.com/private-avatar-thumb.jpg',
        'profile_privacy' => [
            'profile_visible_to' => 'no_one',
        ],
    ]);

    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $response = $this->getJson("/api/hubs/{$this->hub->id}")
        ->assertOk()
        ->assertJsonPath('data.member_preview.0.avatar_thumb_url', null)
        ->assertJsonPath('data.member_preview.0.username', null)
        ->assertJsonPath('data.member_preview.0.is_private', true);

    expect(data_get($response->json(), 'data.member_preview.0.name'))
        ->not->toBe($rawName)
        ->toContain('*');
});

test('hub show response reflects is_member for authenticated user', function (): void {
    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->getJson("/api/hubs/{$this->hub->id}")
        ->assertOk()
        ->assertJsonPath('data.is_member', true);
});
