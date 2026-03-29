<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
}
