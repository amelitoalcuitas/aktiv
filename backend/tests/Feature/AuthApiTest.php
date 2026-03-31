<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_via_api(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'country' => 'Philippines',
            'province' => 'Zamboanga del Sur',
            'city' => 'Pagadian',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'first_name', 'last_name', 'email', 'country', 'province', 'city'],
                'token',
                'token_type',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'country' => 'Philippines',
            'province' => 'Zamboanga del Sur',
            'city' => 'Pagadian',
        ]);
    }

    public function test_registration_requires_location_fields(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'country',
            'province',
            'city',
        ]);
    }

    public function test_user_can_login_and_fetch_profile(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_premium' => true,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('token');

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.is_premium', true)
            ->assertJsonPath('token_type', 'Bearer');

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.is_premium', true);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'wrong@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'incorrect-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_google_redirect_preserves_safe_redirect_path(): void
    {
        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('stateless')->andReturnSelf();
        Socialite::shouldReceive('with')->once()->with(Mockery::on(function (array $payload): bool {
            $decoded = json_decode(base64_decode(strtr($payload['state'], '-_', '+/')), true);

            return is_array($decoded) && $decoded['redirect'] === '/bookings';
        }))->andReturnSelf();
        Socialite::shouldReceive('redirect')->andReturnSelf();
        Socialite::shouldReceive('getTargetUrl')->andReturn('https://accounts.google.com/o/oauth2/auth');

        $this->getJson('/api/auth/google/redirect?redirect=%2Fbookings')
            ->assertOk()
            ->assertJsonPath('url', 'https://accounts.google.com/o/oauth2/auth');

        Mockery::close();
    }

    public function test_google_callback_creates_user_and_redirects_to_frontend_callback(): void
    {
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = 'google-123';
        $socialiteUser->email = 'google-user@example.com';
        $socialiteUser->name = 'Google User';
        $socialiteUser->avatar = 'https://example.com/avatar.png';

        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('stateless')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialiteUser);

        $state = rtrim(strtr(base64_encode(json_encode(['redirect' => '/bookings'])), '+/', '-_'), '=');

        $response = $this->get('/api/auth/google/callback?state='.$state);

        $response
            ->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringContainsString('/auth/google/callback', $location);
        $this->assertStringContainsString('status=needs_profile', $location);
        $this->assertStringContainsString('redirect=%2Fbookings', $location);

        $pendingToken = $this->extractQueryValue($location, 'pending_token');
        $this->assertNotNull($pendingToken);

        $completionResponse = $this->postJson('/api/auth/google/complete', [
            'pending_token' => $pendingToken,
            'country' => 'Philippines',
            'province' => 'Zamboanga del Sur',
            'city' => 'Pagadian',
        ]);

        $completionResponse
            ->assertSuccessful()
            ->assertJsonPath('user.email', 'google-user@example.com')
            ->assertJsonPath('user.google_id', 'google-123')
            ->assertJsonPath('user.country', 'Philippines')
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertDatabaseHas('users', [
            'email' => 'google-user@example.com',
            'google_id' => 'google-123',
            'country' => 'Philippines',
            'province' => 'Zamboanga del Sur',
            'city' => 'Pagadian',
        ]);

        Mockery::close();
    }

    public function test_google_callback_links_existing_user_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'google-user@example.com',
            'password' => Hash::make('password123'),
            'google_id' => null,
            'email_verified_at' => null,
        ]);

        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = 'google-123';
        $socialiteUser->email = 'google-user@example.com';
        $socialiteUser->name = 'Google User';
        $socialiteUser->avatar = 'https://example.com/avatar.png';

        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('stateless')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialiteUser);

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();

        $user->refresh();

        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseCount('users', 1);

        Mockery::close();
    }

    public function test_google_callback_redirects_incomplete_existing_user_to_profile_completion(): void
    {
        $user = User::factory()->create([
            'email' => 'google-user@example.com',
            'password' => null,
            'google_id' => null,
            'country' => null,
            'province' => null,
            'city' => null,
            'email_verified_at' => null,
        ]);

        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = 'google-123';
        $socialiteUser->email = 'google-user@example.com';
        $socialiteUser->name = 'Google User';
        $socialiteUser->avatar = 'https://example.com/avatar.png';

        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('stateless')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialiteUser);

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('status=needs_profile', $location);

        $pendingToken = $this->extractQueryValue($location, 'pending_token');
        $this->assertNotNull($pendingToken);

        $completionResponse = $this->postJson('/api/auth/google/complete', [
            'pending_token' => $pendingToken,
            'country' => 'Philippines',
            'province' => 'Zamboanga del Sur',
            'city' => 'Pagadian',
        ]);

        $completionResponse
            ->assertSuccessful()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.google_id', 'google-123');

        $user->refresh();
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('Philippines', $user->country);
        $this->assertSame('Zamboanga del Sur', $user->province);
        $this->assertSame('Pagadian', $user->city);

        Mockery::close();
    }

    public function test_google_callback_does_not_replace_existing_avatar(): void
    {
        $user = User::factory()->create([
            'email' => 'google-user@example.com',
            'avatar_url' => 'https://example.com/existing-avatar.png',
        ]);

        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = 'google-123';
        $socialiteUser->email = 'google-user@example.com';
        $socialiteUser->name = 'Google User';
        $socialiteUser->avatar = 'https://example.com/google-avatar.png';

        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('stateless')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialiteUser);

        $this->get('/api/auth/google/callback')->assertRedirect();

        $user->refresh();

        $this->assertSame('https://example.com/existing-avatar.png', $user->avatar_url);
        $this->assertSame('google-123', $user->google_id);

        Mockery::close();
    }

    public function test_google_callback_redirects_with_error_when_oauth_fails(): void
    {
        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('stateless')->andReturnSelf();
        Socialite::shouldReceive('user')->andThrow(ValidationException::withMessages([
            'email' => ['Google account does not provide an email address.'],
        ]));

        $response = $this->get('/api/auth/google/callback?state=' . rtrim(strtr(base64_encode(json_encode([
            'redirect' => '/bookings',
        ])), '+/', '-_'), '='));

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringContainsString('status=error', $location);
        $this->assertStringContainsString('reason=oauth_failed', $location);
        $this->assertStringContainsString('redirect=%2Fbookings', $location);

        Mockery::close();
    }

    public function test_google_complete_signup_rejects_expired_pending_token(): void
    {
        $this->postJson('/api/auth/google/complete', [
            'pending_token' => 'expired-token',
            'country' => 'Philippines',
            'province' => 'Zamboanga del Sur',
            'city' => 'Pagadian',
        ])->assertUnprocessable()->assertJsonValidationErrors('pending_token');
    }

    private function extractQueryValue(string $location, string $key): ?string
    {
        $queryString = parse_url($location, PHP_URL_QUERY);

        if (! is_string($queryString) || $queryString === '') {
            return null;
        }

        parse_str($queryString, $query);

        return isset($query[$key]) && is_string($query[$key]) ? $query[$key] : null;
    }
}
