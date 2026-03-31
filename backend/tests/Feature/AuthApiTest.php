<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_google_callback_creates_user_and_returns_token(): void
    {
        $socialiteUser = new SocialiteUser();
        $socialiteUser->id = 'google-123';
        $socialiteUser->email = 'google-user@example.com';
        $socialiteUser->name = 'Google User';
        $socialiteUser->avatar = 'https://example.com/avatar.png';

        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('stateless')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialiteUser);

        $response = $this->getJson('/api/auth/google/callback');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'email', 'google_id'],
                'token',
                'token_type',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'google-user@example.com',
            'google_id' => 'google-123',
        ]);

        Mockery::close();
    }
}
