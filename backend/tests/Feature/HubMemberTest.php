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
    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->getJson("/api/hubs/{$this->hub->id}/members")
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total']])
        ->assertJsonPath('meta.total', 1);
});

test('unauthenticated user can list members', function (): void {
    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->getJson("/api/hubs/{$this->hub->id}/members/list")
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonCount(1, 'data');
});

test('authenticated user can join a hub', function (): void {
    $this->actingAs($this->user)
        ->postJson("/api/hubs/{$this->hub->id}/members")
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'username', 'avatar_thumb_url']]);

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

test('hub owner cannot join their own hub', function (): void {
    $this->actingAs($this->owner)
        ->postJson("/api/hubs/{$this->hub->id}/members")
        ->assertForbidden();
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
    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->getJson("/api/hubs/{$this->hub->id}")
        ->assertOk()
        ->assertJsonStructure(['data' => ['members_count', 'member_preview', 'is_member']])
        ->assertJsonPath('data.members_count', 1)
        ->assertJsonPath('data.is_member', false);
});

test('hub show response reflects is_member for authenticated user', function (): void {
    HubMember::create(['hub_id' => $this->hub->id, 'user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->getJson("/api/hubs/{$this->hub->id}")
        ->assertOk()
        ->assertJsonPath('data.is_member', true);
});
