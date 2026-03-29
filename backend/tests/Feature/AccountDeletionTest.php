<?php

namespace Tests\Feature;

use App\Console\Commands\PurgeScheduledDeletions;
use App\Mail\AccountDeletionScheduled;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_deletion_with_correct_password(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => Hash::make('secret')]);

        $this->actingAs($user)
            ->postJson('/api/profile/request-deletion', ['current_password' => 'secret'])
            ->assertOk();

        $this->assertNotNull($user->fresh()->deletion_scheduled_at);
    }

    public function test_deletion_email_is_queued_when_account_deletion_is_requested(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => Hash::make('secret')]);

        $this->actingAs($user)
            ->postJson('/api/profile/request-deletion', ['current_password' => 'secret'])
            ->assertOk();

        Mail::assertQueued(AccountDeletionScheduled::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_deletion_request_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret')]);

        $this->actingAs($user)
            ->postJson('/api/profile/request-deletion', ['current_password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertNull($user->fresh()->deletion_scheduled_at);
    }

    public function test_user_can_cancel_pending_deletion(): void
    {
        $user = User::factory()->create([
            'password'              => Hash::make('secret'),
            'deletion_scheduled_at' => now()->addDays(25),
        ]);

        $this->actingAs($user)
            ->postJson('/api/profile/cancel-deletion')
            ->assertOk();

        $this->assertNull($user->fresh()->deletion_scheduled_at);
    }

    public function test_pending_deletion_user_profile_is_not_publicly_accessible(): void
    {
        $user = User::factory()->create(['deletion_scheduled_at' => now()->addDays(10)]);

        $this->getJson('/api/users/' . $user->id)->assertNotFound();
    }

    public function test_pending_deletion_user_cannot_be_resolved_by_username(): void
    {
        $user = User::factory()->create([
            'username'              => 'deleteme',
            'deletion_scheduled_at' => now()->addDays(10),
        ]);

        $this->getJson('/api/users/resolve/deleteme')->assertNotFound();
    }

    public function test_purge_command_permanently_deletes_expired_accounts(): void
    {
        $expired = User::factory()->create(['deletion_scheduled_at' => now()->subDay()]);
        $notYet  = User::factory()->create(['deletion_scheduled_at' => now()->addDays(5)]);
        $normal  = User::factory()->create();

        $this->artisan(PurgeScheduledDeletions::class)->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $expired->id]);
        $this->assertDatabaseHas('users', ['id' => $notYet->id]);
        $this->assertDatabaseHas('users', ['id' => $normal->id]);
    }
}
