<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function redirect(): JsonResponse
    {
        $url = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'url' => $url,
        ]);
    }

    public function redirectForDeletion(Request $request): JsonResponse
    {
        $state = base64_encode(json_encode([
            'action'  => 'delete_account',
            'user_id' => $request->user()->id,
        ]));

        $url = Socialite::driver('google')
            ->stateless()
            ->with(['prompt' => 'consent', 'state' => $state])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function deletionCallback(Request $request): RedirectResponse
    {
        $rawState = $request->query('state', '');
        $state    = json_decode(base64_decode((string) $rawState), true);

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

    public function callback(): JsonResponse
    {
        $socialiteUser = Socialite::driver('google')->stateless()->user();
        $email = $socialiteUser->getEmail();

        if (! $email) {
            throw ValidationException::withMessages([
                'email' => ['Google account does not provide an email address.'],
            ]);
        }

        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        if (! $user->exists) {
            $parts = explode(' ', $socialiteUser->getName() ?: 'Google User', 2);
            $user->first_name = $parts[0];
            $user->last_name = $parts[1] ?? '';
            $user->role = UserRole::User;
        }

        $user->google_id = $socialiteUser->getId();
        $user->avatar_url = $socialiteUser->getAvatar();
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'       => new ProfileResource($user),
            'token'      => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
