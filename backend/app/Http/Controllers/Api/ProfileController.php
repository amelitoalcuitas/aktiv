<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
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

        if (isset($data['profile_privacy'])) {
            $data['profile_privacy'] = array_merge(
                $request->user()->profile_privacy ?? [],
                $data['profile_privacy']
            );
        }

        $request->user()->update($data);

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
