<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Http\Requests\Auth\CompleteGoogleSignupRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const VERIFICATION_EMAIL_COOLDOWN_SECONDS = 300;

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create(array_merge($request->validated(), ['role' => UserRole::User]));
        $user->update(['username' => User::generateUsername($user->first_name, $user->last_name)]);
        $user->sendEmailVerificationNotification();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'                 => new ProfileResource($user),
            'token'                => $token,
            'token_type'           => 'Bearer',
            'requires_verification' => true,
        ], 201);
    }

    public function completeGoogleSignup(CompleteGoogleSignupRequest $request): JsonResponse
    {
        $data = $request->validated();
        $cacheKey = $this->googleSignupCacheKey($data['pending_token']);
        $pending = Cache::pull($cacheKey);

        if (! is_array($pending)) {
            throw ValidationException::withMessages([
                'pending_token' => ['Google sign-in session expired. Please sign in with Google again.'],
            ]);
        }

        $email = $pending['email'] ?? null;
        $googleId = $pending['google_id'] ?? null;
        $firstName = $pending['first_name'] ?? null;
        $lastName = $pending['last_name'] ?? null;
        $avatarUrl = $pending['avatar_url'] ?? null;
        $userId = $pending['user_id'] ?? null;

        if (! is_string($email) || $email === '' || ! is_string($googleId) || $googleId === '') {
            throw ValidationException::withMessages([
                'pending_token' => ['Google sign-in session expired. Please sign in with Google again.'],
            ]);
        }

        $user = null;

        if (is_string($userId) && $userId !== '') {
            $user = User::query()->find($userId);
        }

        if (! $user) {
            $user = User::query()->firstOrNew(['email' => $email]);
        }

        if (! $user->exists) {
            $user->first_name = is_string($firstName) && $firstName !== '' ? $firstName : 'Google';
            $user->last_name = is_string($lastName) ? $lastName : '';
            $user->role = UserRole::User;
            $user->username = User::generateUsername($user->first_name, $user->last_name);
        } elseif (! $user->username) {
            $user->username = User::generateUsername(
                $user->first_name ?: 'Google',
                $user->last_name ?: 'User'
            );
        }

        $user->email = $email;
        $user->google_id = $googleId;
        $user->avatar_url = $user->avatar_url ?: (is_string($avatarUrl) ? $avatarUrl : null);
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->country = $data['country'];
        $user->province = $data['province'];
        $user->city = $data['city'];
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'       => new ProfileResource($user->fresh()),
            'token'      => $token,
            'token_type' => 'Bearer',
        ], $user->wasRecentlyCreated ? 201 : 200);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'       => new ProfileResource($user),
            'token'      => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new ProfileResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:8080');

        if (! URL::hasValidSignature($request)) {
            return redirect($frontendUrl . '/auth/verified?status=invalid');
        }

        $user = User::query()->find($id);

        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect($frontendUrl . '/auth/verified?status=invalid');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect($frontendUrl . '/auth/verified?status=success');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['already_verified' => true]);
        }

        $cooldown = $this->verificationCooldown($request);

        if ($cooldown['is_active']) {
            return response()
                ->json([
                    'message' => 'Please wait before requesting another verification email.',
                    'cooldown' => $cooldown,
                ], 429)
                ->header('Retry-After', (string) $cooldown['remaining_seconds']);
        }

        $user->sendEmailVerificationNotification();
        RateLimiter::hit($this->verificationThrottleKey($request), self::VERIFICATION_EMAIL_COOLDOWN_SECONDS);

        return response()->json([
            'message' => 'Verification email sent.',
            'cooldown' => $this->verificationCooldown($request),
        ]);
    }

    public function resendVerificationStatus(Request $request): JsonResponse
    {
        return response()->json([
            'cooldown' => $this->verificationCooldown($request),
        ]);
    }

    private function verificationThrottleKey(Request $request): string
    {
        return 'auth:email-resend:' . $request->user()->getAuthIdentifier();
    }

    /**
     * @return array{is_active: bool, remaining_seconds: int, available_at: ?string}
     */
    private function verificationCooldown(Request $request): array
    {
        $remainingSeconds = RateLimiter::availableIn($this->verificationThrottleKey($request));

        return [
            'is_active' => $remainingSeconds > 0,
            'remaining_seconds' => $remainingSeconds,
            'available_at' => $remainingSeconds > 0
                ? now()->addSeconds($remainingSeconds)->toIso8601String()
                : null,
        ];
    }

    private function googleSignupCacheKey(string $token): string
    {
        return 'google_signup:' . $token;
    }
}
