<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Mail\AccountDeletionScheduled;
use App\Notifications\ChangePasswordNotification;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    private const CHANGE_PASSWORD_EMAIL_COOLDOWN_SECONDS = 300;

    public function __construct(
        private readonly ImageUploadService $imageUploadService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => new ProfileResource($request->user())]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Rate-limit: name (first_name or last_name) — once every 3 months
        $changingName = isset($data['first_name']) || isset($data['last_name']);
        if ($changingName && $user->name_changed_at && $user->name_changed_at->addMonths(3)->isFuture()) {
            throw ValidationException::withMessages([
                'first_name' => [
                    'You can only change your name once every 3 months. Next allowed: '
                    . $user->name_changed_at->addMonths(3)->toFormattedDateString() . '.',
                ],
            ]);
        }

        // Rate-limit: username — once every 1 month (only if actually changing to a different value)
        $changingUsername = isset($data['username']) && $data['username'] !== $user->username;
        if ($changingUsername && $user->username_changed_at && $user->username_changed_at->addMonth()->isFuture()) {
            throw ValidationException::withMessages([
                'username' => [
                    'You can only change your username once a month. Next allowed: '
                    . $user->username_changed_at->addMonth()->toFormattedDateString() . '.',
                ],
            ]);
        }

        if (isset($data['profile_privacy'])) {
            $data['profile_privacy'] = array_merge(
                $user->profile_privacy ?? [],
                $data['profile_privacy']
            );
        }

        if (array_key_exists('hub_display_order', $data)) {
            $data['hub_display_order'] = $data['hub_display_order'] ?? [];
        }

        if ($changingName) {
            $data['name_changed_at'] = now();
        }

        if ($changingUsername) {
            $data['username_changed_at'] = now();
        }

        $user->update($data);

        return response()->json(['data' => new ProfileResource($user->fresh())]);
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $user = $request->user();

        $isGoogleOnly = $user->google_id && ! $user->password;

        if ($isGoogleOnly) {
            $request->validate([
                'deletion_token' => ['required', 'string'],
            ]);

            $cacheKey = 'deletion_token:' . $user->id;
            $storedToken = Cache::get($cacheKey);

            if (! $storedToken || ! hash_equals($storedToken, $request->deletion_token)) {
                throw ValidationException::withMessages([
                    'deletion_token' => ['Invalid or expired verification token. Please re-authenticate with Google.'],
                ]);
            }

            Cache::forget($cacheKey);
        } else {
            $request->validate([
                'current_password' => ['required', 'string'],
            ]);

            if (! Hash::check($request->current_password, $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The password you entered is incorrect.'],
                ]);
            }
        }

        $user->update(['deletion_scheduled_at' => now()->addDays(30)]);
        $user->tokens()->delete();

        Mail::to($user)->queue(new AccountDeletionScheduled($user->fresh()));

        return response()->json(['message' => 'Account deletion scheduled.']);
    }

    public function cancelDeletion(Request $request): JsonResponse
    {
        $request->user()->update(['deletion_scheduled_at' => null]);

        return response()->json(['data' => new ProfileResource($request->user()->fresh())]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:10240'],
        ]);

        $user = $request->user();

        if ($user->avatar_url && $this->isStorageUrl($user->avatar_url)) {
            Storage::disk('s3')->delete($this->pathFromUrl($user->avatar_url));
        }

        if ($user->avatar_thumb_url && $this->isStorageUrl($user->avatar_thumb_url)) {
            Storage::disk('s3')->delete($this->pathFromUrl($user->avatar_thumb_url));
        }

        $result = $this->imageUploadService->uploadAvatar($request->file('avatar'));

        $user->update([
            'avatar_url'       => $result['avatar']['url'],
            'avatar_thumb_url' => $result['thumb']['url'],
        ]);

        return response()->json(['data' => new ProfileResource($user->fresh())]);
    }

    public function uploadBanner(Request $request): JsonResponse
    {
        $request->validate([
            'banner' => ['required', 'image', 'max:10240'],
        ]);

        $user = $request->user();

        if ($user->banner_url && $this->isStorageUrl($user->banner_url)) {
            Storage::disk('s3')->delete($this->pathFromUrl($user->banner_url));
        }

        $result = $this->imageUploadService->upload($request->file('banner'), 'banners');

        $user->update(['banner_url' => $result['url']]);

        return response()->json(['data' => new ProfileResource($user->fresh())]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $cooldown = $this->changePasswordCooldown($request);

        if ($cooldown['is_active']) {
            return response()
                ->json([
                    'message' => 'Please wait before requesting another password change email.',
                    'cooldown' => $cooldown,
                ], 429)
                ->header('Retry-After', (string) $cooldown['remaining_seconds']);
        }

        $token = Password::createToken($user);
        $user->notify(new ChangePasswordNotification($token));
        RateLimiter::hit($this->changePasswordThrottleKey($request), self::CHANGE_PASSWORD_EMAIL_COOLDOWN_SECONDS);

        return response()->json([
            'message' => 'A password change link has been sent to your email.',
            'cooldown' => $this->changePasswordCooldown($request),
        ]);
    }

    public function changePasswordStatus(Request $request): JsonResponse
    {
        return response()->json([
            'cooldown' => $this->changePasswordCooldown($request),
        ]);
    }

    private function changePasswordThrottleKey(Request $request): string
    {
        return 'profile:change-password:' . $request->user()->getAuthIdentifier();
    }

    /**
     * @return array{is_active: bool, remaining_seconds: int, available_at: ?string}
     */
    private function changePasswordCooldown(Request $request): array
    {
        $remainingSeconds = RateLimiter::availableIn($this->changePasswordThrottleKey($request));

        return [
            'is_active' => $remainingSeconds > 0,
            'remaining_seconds' => $remainingSeconds,
            'available_at' => $remainingSeconds > 0
                ? now()->addSeconds($remainingSeconds)->toIso8601String()
                : null,
        ];
    }

    private function isStorageUrl(string $url): bool
    {
        return str_contains($url, config('filesystems.disks.s3.url', ''));
    }

    private function pathFromUrl(string $url): string
    {
        $base = rtrim((string) config('filesystems.disks.s3.url'), '/');

        return ltrim(str_replace($base, '', $url), '/');
    }
}
