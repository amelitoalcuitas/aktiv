<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function redirect(Request $request): \Illuminate\Http\JsonResponse
    {
        $frontendRedirect = $this->sanitizeFrontendRedirect($request->query('redirect'));
        $state = $this->encodeState([
            'redirect' => $frontendRedirect,
        ]);

        $url = Socialite::driver('google')
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'url' => $url,
        ]);
    }

    public function redirectForDeletion(Request $request): \Illuminate\Http\JsonResponse
    {
        $state = $this->encodeState([
            'action'  => 'delete_account',
            'user_id' => $request->user()->id,
        ]);

        $url = Socialite::driver('google')
            ->stateless()
            ->with(['prompt' => 'consent', 'state' => $state])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function deletionCallback(Request $request): RedirectResponse
    {
        $state = $this->decodeState($request->query('state'));

        $frontendBase = config('app.frontend_url', 'http://localhost:8080');

        if (! is_array($state) || ($state['action'] ?? '') !== 'delete_account' || empty($state['user_id'])) {
            return redirect($frontendBase . '/settings?deletion_error=invalid_state');
        }

        try {
            $socialiteUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable) {
            return redirect($frontendBase . '/settings?deletion_error=oauth_failed');
        }

        $user = User::query()->find($state['user_id']);

        if (! $user || $user->google_id !== $socialiteUser->getId()) {
            return redirect($frontendBase . '/settings?deletion_error=account_mismatch');
        }

        $token    = Str::random(64);
        $cacheKey = 'deletion_token:' . $user->id;
        Cache::put($cacheKey, $token, now()->addMinutes(5));

        return redirect($frontendBase . '/settings?deletion_token=' . $token);
    }

    public function callback(Request $request): RedirectResponse
    {
        $frontendBase = config('app.frontend_url', 'http://localhost:8080');
        $frontendPath = '/auth/google/callback';
        $redirectPath = '/dashboard';

        $state = $this->decodeState($request->query('state'));
        if (is_array($state)) {
            $redirectPath = $this->sanitizeFrontendRedirect($state['redirect'] ?? null);
        }

        try {
            $socialiteUser = Socialite::driver('google')->stateless()->user();
            $email = $socialiteUser->getEmail();

            if (! $email) {
                throw ValidationException::withMessages([
                    'email' => ['Google account does not provide an email address.'],
                ]);
            }

            $user = User::query()->where('email', $email)->first();

            if ($user && $this->hasCompleteLocation($user)) {
                if (! $user->username) {
                    $user->username = User::generateUsername(
                        $user->first_name ?: 'Google',
                        $user->last_name ?: 'User'
                    );
                }

                $user->google_id = $socialiteUser->getId();
                $user->avatar_url = $user->avatar_url ?: $socialiteUser->getAvatar();
                $user->email_verified_at = $user->email_verified_at ?: now();
                $user->save();

                $token = $user->createToken('auth_token')->plainTextToken;
            } else {
                $pendingToken = $this->createPendingGoogleSignup([
                    'email' => $email,
                    'google_id' => $socialiteUser->getId(),
                    'first_name' => $this->googleFirstName($socialiteUser->getName()),
                    'last_name' => $this->googleLastName($socialiteUser->getName()),
                    'avatar_url' => $socialiteUser->getAvatar(),
                    'user_id' => $user?->id,
                ]);

                return redirect($this->buildFrontendCallbackUrl(
                    $frontendBase,
                    $frontendPath,
                    [
                        'status' => 'needs_profile',
                        'pending_token' => $pendingToken,
                        'redirect' => $redirectPath,
                    ]
                ));
            }
        } catch (\Throwable) {
            return redirect($this->buildFrontendCallbackUrl(
                $frontendBase,
                $frontendPath,
                [
                    'status' => 'error',
                    'reason' => 'oauth_failed',
                    'redirect' => $redirectPath,
                ]
            ));
        }

        return redirect($this->buildFrontendCallbackUrl(
            $frontendBase,
            $frontendPath,
            [
                'status' => 'success',
                'token' => $token,
                'redirect' => $redirectPath,
            ]
        ));
    }

    private function sanitizeFrontendRedirect(mixed $redirect): string
    {
        if (! is_string($redirect) || ! str_starts_with($redirect, '/')) {
            return '/dashboard';
        }

        return str_starts_with($redirect, '//') ? '/dashboard' : $redirect;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeState(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeState(mixed $state): ?array
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        $decoded = base64_decode(strtr($state, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        try {
            $json = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($json) ? $json : null;
    }

    private function hasCompleteLocation(User $user): bool
    {
        return filled($user->country) && filled($user->province) && filled($user->city);
    }

    private function createPendingGoogleSignup(array $payload): string
    {
        $token = Str::random(64);
        Cache::put($this->pendingGoogleSignupCacheKey($token), $payload, now()->addMinutes(15));

        return $token;
    }

    private function pendingGoogleSignupCacheKey(string $token): string
    {
        return 'google_signup:' . $token;
    }

    private function googleFirstName(?string $name): string
    {
        $parts = explode(' ', trim((string) $name), 2);

        return $parts[0] !== '' ? $parts[0] : 'Google';
    }

    private function googleLastName(?string $name): string
    {
        $parts = explode(' ', trim((string) $name), 2);

        return $parts[1] ?? '';
    }

    /**
     * @param  array<string, string>  $query
     */
    private function buildFrontendCallbackUrl(string $frontendBase, string $path, array $query): string
    {
        return $frontendBase . $path . '?' . http_build_query($query);
    }
}
