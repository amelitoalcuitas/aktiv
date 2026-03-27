<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageUploadService
{
    private const MAX_TARGET_BYTES = 512000; // 500KB

    /**
     * Upload an avatar, returning a full-size (400×400) and thumb (80×80) variant.
     *
     * @return array{
     *     avatar: array{path: string, url: string},
     *     thumb:  array{path: string, url: string},
     * }
     */
    public function uploadAvatar(UploadedFile $file): array
    {
        $manager = new ImageManager(new Driver());

        $avatarImage = $manager->read($file->getRealPath());
        $avatarImage->cover(400, 400);
        $avatarPath = 'avatars/'.(string) str()->uuid().'.jpg';
        Storage::disk('s3')->put($avatarPath, $avatarImage->toJpeg(85)->toString(), [
            'visibility' => 'public',
            'ContentType' => 'image/jpeg',
        ]);

        $thumbImage = $manager->read($file->getRealPath());
        $thumbImage->cover(80, 80);
        $thumbPath = 'avatars/thumbs/'.(string) str()->uuid().'.jpg';
        Storage::disk('s3')->put($thumbPath, $thumbImage->toJpeg(85)->toString(), [
            'visibility' => 'public',
            'ContentType' => 'image/jpeg',
        ]);

        return [
            'avatar' => [
                'path' => $avatarPath,
                'url'  => Storage::disk('s3')->url($avatarPath),
            ],
            'thumb' => [
                'path' => $thumbPath,
                'url'  => Storage::disk('s3')->url($thumbPath),
            ],
        ];
    }

    /**
     * @return array{path: string, url: string}
     */
    public function upload(UploadedFile $file, string $folder): array
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath());

        // Resize down to fit within 1080p boundary while keeping aspect ratio.
        $image->scaleDown(width: 1920, height: 1080);

        $quality = 85;
        $encoded = $image->toJpeg($quality);

        while (strlen($encoded->toString()) > self::MAX_TARGET_BYTES && $quality > 60) {
            $quality -= 5;
            $encoded = $image->toJpeg($quality);
        }

        $path = trim($folder, '/').'/'.(string) str()->uuid().'.jpg';

        Storage::disk('s3')->put($path, $encoded->toString(), [
            'visibility' => 'public',
            'ContentType' => 'image/jpeg',
        ]);

        return [
            'path' => $path,
            'url' => Storage::disk('s3')->url($path),
        ];
    }
}
