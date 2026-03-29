<?php

use App\Enums\HubOwnerRequestStatus;
use App\Enums\UserRole;
use App\Mail\HubOwnerApplicationApproved;
use App\Mail\HubOwnerApplicationRejected;
use App\Mail\HubOwnerApplicationSubmitted;
use App\Models\HubOwnerRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function makeSuperAdmin(array $attributes = []): User
{
    return User::factory()->admin()->create(array_merge([
        'role' => UserRole::SuperAdmin,
    ], $attributes));
}

it('regular user can create a hub owner request', function () {
    Mail::fake();

    $user = User::factory()->create([
        'contact_number' => '09171234567',
    ]);
    makeSuperAdmin();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/hub-owner-request', [
        'hub_name' => 'Baseline Sports Hub',
        'city' => 'Manila',
        'contact_number' => '09171234567',
        'message' => 'I want to onboard our local courts.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.hub_name', 'Baseline Sports Hub')
        ->assertJsonPath('data.city', 'Manila')
        ->assertJsonPath('data.contact_number', '09171234567');

    $this->assertDatabaseHas('hub_owner_requests', [
        'user_id' => $user->id,
        'status' => HubOwnerRequestStatus::Pending->value,
        'hub_name' => 'Baseline Sports Hub',
    ]);
});

it('queues one submission email per super admin when a request is created', function () {
    Mail::fake();

    $user = User::factory()->create();
    $superAdminOne = makeSuperAdmin(['email' => 'sa1@example.com']);
    $superAdminTwo = makeSuperAdmin(['email' => 'sa2@example.com']);

    $this->actingAs($user, 'sanctum')->postJson('/api/hub-owner-request', [
        'message' => 'Please approve me as a hub owner.',
    ])->assertCreated();

    Mail::assertQueued(HubOwnerApplicationSubmitted::class, 2);
    Mail::assertQueued(HubOwnerApplicationSubmitted::class, fn (HubOwnerApplicationSubmitted $mail) => $mail->hasTo($superAdminOne->email));
    Mail::assertQueued(HubOwnerApplicationSubmitted::class, fn (HubOwnerApplicationSubmitted $mail) => $mail->hasTo($superAdminTwo->email));
});

it('blocks duplicate pending hub owner requests', function () {
    Mail::fake();

    $user = User::factory()->create();

    HubOwnerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => HubOwnerRequestStatus::Pending,
    ]);

    $this->actingAs($user, 'sanctum')->postJson('/api/hub-owner-request', [
        'message' => 'Trying again.',
    ])->assertStatus(409);

    Mail::assertNothingQueued();
});

it('blocks resubmission after a rejected request in this pass', function () {
    Mail::fake();

    $user = User::factory()->create();

    HubOwnerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => HubOwnerRequestStatus::Rejected,
    ]);

    $this->actingAs($user, 'sanctum')->postJson('/api/hub-owner-request', [
        'message' => 'Trying again.',
    ])->assertStatus(409);

    Mail::assertNothingQueued();
});

it('admin cannot create a hub owner request', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'sanctum')->postJson('/api/hub-owner-request', [
        'message' => 'I should not be allowed.',
    ])->assertForbidden();
});

it('super admin cannot create a hub owner request', function () {
    Mail::fake();

    $superAdmin = makeSuperAdmin();

    $this->actingAs($superAdmin, 'sanctum')->postJson('/api/hub-owner-request', [
        'message' => 'I should not be allowed.',
    ])->assertForbidden();
});

it('hub owner request endpoint returns null when user has no request', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/hub-owner-request')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('hub owner request endpoint returns the latest request summary', function () {
    $user = User::factory()->create();

    HubOwnerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => HubOwnerRequestStatus::Rejected,
        'created_at' => now()->subDay(),
    ]);

    $latest = HubOwnerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => HubOwnerRequestStatus::Pending,
        'message' => 'Newest request',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/hub-owner-request')
        ->assertOk()
        ->assertJsonPath('data.id', $latest->id)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.message', 'Newest request');
});

it('auth me payload includes hub owner request status', function () {
    $user = User::factory()->create();

    HubOwnerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => HubOwnerRequestStatus::Pending,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('user.hub_owner_request_status', 'pending');
});

it('super admin can list hub owner requests', function () {
    $superAdmin = makeSuperAdmin();
    $applicant = User::factory()->create([
        'first_name' => 'Jamie',
        'last_name' => 'Santos',
        'email' => 'jamie@example.com',
    ]);

    $hubOwnerRequest = HubOwnerRequest::factory()->create([
        'user_id' => $applicant->id,
        'status' => HubOwnerRequestStatus::Pending,
    ]);

    $this->actingAs($superAdmin, 'sanctum')
        ->getJson('/api/panel/hub-owner-requests')
        ->assertOk()
        ->assertJsonPath('data.0.id', $hubOwnerRequest->id)
        ->assertJsonPath('data.0.user.name', 'Jamie Santos')
        ->assertJsonPath('data.0.user.email', 'jamie@example.com');
});

it('non super admin cannot list hub owner requests', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/panel/hub-owner-requests')
        ->assertForbidden();
});

it('super admin can approve a pending request and promote the user to admin', function () {
    Mail::fake();

    $superAdmin = makeSuperAdmin();
    $user = User::factory()->create([
        'first_name' => 'Chris',
        'last_name' => 'Tan',
        'email' => 'chris@example.com',
    ]);

    $hubOwnerRequest = HubOwnerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => HubOwnerRequestStatus::Pending,
    ]);

    $response = $this->actingAs($superAdmin, 'sanctum')
        ->postJson("/api/panel/hub-owner-requests/{$hubOwnerRequest->id}/approve");

    $response->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.user.email', 'chris@example.com');

    $hubOwnerRequest->refresh();

    expect($hubOwnerRequest->status)->toBe(HubOwnerRequestStatus::Approved)
        ->and($hubOwnerRequest->reviewed_by)->toBe($superAdmin->id)
        ->and($hubOwnerRequest->reviewed_at)->not->toBeNull()
        ->and($user->fresh()->role)->toBe(UserRole::Admin);

    Mail::assertQueued(HubOwnerApplicationApproved::class, function (HubOwnerApplicationApproved $mail) use ($user) {
        $html = $mail->buildViewData();
        $rendered = $mail->render();

        return $mail->hasTo($user->email)
            && str_contains($rendered, '/dashboard')
            && str_contains($rendered, 'How to create your first hub');
    });
});

it('super admin can reject a pending request without changing the user role', function () {
    Mail::fake();

    $superAdmin = makeSuperAdmin();
    $user = User::factory()->create([
        'email' => 'applicant@example.com',
    ]);

    $hubOwnerRequest = HubOwnerRequest::factory()->create([
        'user_id' => $user->id,
        'status' => HubOwnerRequestStatus::Pending,
    ]);

    $this->actingAs($superAdmin, 'sanctum')
        ->postJson("/api/panel/hub-owner-requests/{$hubOwnerRequest->id}/reject", [
            'review_notes' => 'Please complete your venue details first.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.review_notes', 'Please complete your venue details first.');

    $hubOwnerRequest->refresh();

    expect($hubOwnerRequest->status)->toBe(HubOwnerRequestStatus::Rejected)
        ->and($hubOwnerRequest->reviewed_by)->toBe($superAdmin->id)
        ->and($hubOwnerRequest->review_notes)->toBe('Please complete your venue details first.')
        ->and($user->fresh()->role)->toBe(UserRole::User);

    Mail::assertQueued(HubOwnerApplicationRejected::class, function (HubOwnerApplicationRejected $mail) use ($user) {
        $rendered = $mail->render();

        return $mail->hasTo($user->email)
            && str_contains($rendered, 'Please complete your venue details first.');
    });
});

it('non super admin cannot approve or reject hub owner requests', function () {
    $admin = User::factory()->admin()->create();
    $hubOwnerRequest = HubOwnerRequest::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/panel/hub-owner-requests/{$hubOwnerRequest->id}/approve")
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/panel/hub-owner-requests/{$hubOwnerRequest->id}/reject")
        ->assertForbidden();
});

it('approved request cannot be rejected again', function () {
    Mail::fake();

    $superAdmin = makeSuperAdmin();
    $hubOwnerRequest = HubOwnerRequest::factory()->create([
        'status' => HubOwnerRequestStatus::Approved,
    ]);

    $this->actingAs($superAdmin, 'sanctum')
        ->postJson("/api/panel/hub-owner-requests/{$hubOwnerRequest->id}/reject")
        ->assertStatus(422);
});

it('rejected request cannot be approved again', function () {
    Mail::fake();

    $superAdmin = makeSuperAdmin();
    $hubOwnerRequest = HubOwnerRequest::factory()->create([
        'status' => HubOwnerRequestStatus::Rejected,
    ]);

    $this->actingAs($superAdmin, 'sanctum')
        ->postJson("/api/panel/hub-owner-requests/{$hubOwnerRequest->id}/approve")
        ->assertStatus(422);
});
