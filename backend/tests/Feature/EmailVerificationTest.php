<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'first_name'            => 'Jane',
            'last_name'             => 'Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
          ->assertJsonFragment(['requires_verification' => true]);

        $user = User::query()->where('email', 'jane@example.com')->first();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_assigns_user_role(): void
    {
        Notification::fake(); // prevent actual email sending

        $this->postJson('/api/auth/register', [
            'first_name'            => 'Jane',
            'last_name'             => 'Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'jane@example.com')->first();
        $this->assertEquals(UserRole::User, $user->role);
        $this->assertNull($user->email_verified_at);
    }

    public function test_verify_email_with_valid_signed_url(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($url);

        $response->assertRedirect();
        $this->assertStringContainsString('status=success', $response->headers->get('Location'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verify_email_with_invalid_signature_redirects_to_invalid(): void
    {
        $user = User::factory()->unverified()->create();

        $url = route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]);

        $response = $this->get($url);

        $response->assertRedirect();
        $this->assertStringContainsString('status=invalid', $response->headers->get('Location'));
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verify_email_with_wrong_hash_redirects_to_invalid(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => 'wronghash']
        );

        $response = $this->get($url);

        $response->assertRedirect();
        $this->assertStringContainsString('status=invalid', $response->headers->get('Location'));
    }

    public function test_resend_verification_sends_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/auth/email/resend-verification')
             ->assertOk()
             ->assertJsonFragment(['message' => 'Verification email sent.']);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_verification_returns_already_verified_when_verified(): void
    {
        Notification::fake();

        $user = User::factory()->create(); // verified by default

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/auth/email/resend-verification')
             ->assertOk()
             ->assertJsonFragment(['already_verified' => true]);

        Notification::assertNothingSent();
    }

    public function test_resend_verification_is_rate_limited_for_five_minutes(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/email/resend-verification')
            ->assertOk()
            ->assertJsonPath('cooldown.is_active', true);

        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/email/resend-verification')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('cooldown.is_active', true);

        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);
    }

    public function test_resend_verification_status_returns_remaining_cooldown(): void
    {
        $user = User::factory()->unverified()->create();

        RateLimiter::hit('auth:email-resend:' . $user->id, 300);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/email/resend-verification/status')
            ->assertOk()
            ->assertJsonPath('cooldown.is_active', true)
            ->assertJsonPath('cooldown.remaining_seconds', 300);
    }
}
