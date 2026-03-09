<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
            $user->name = $socialiteUser->getName() ?: 'Google User';
        }

        $user->google_id = $socialiteUser->getId();
        $user->avatar_url = $socialiteUser->getAvatar();
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
