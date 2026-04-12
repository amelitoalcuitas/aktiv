<?php

use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

it('throws and logs when avatar upload cannot be written to object storage', function () {
    config()->set('filesystems.disks.s3.bucket', 'aktiv-media');
    config()->set('filesystems.disks.s3.endpoint', 'http://minio:9000');
    config()->set('filesystems.disks.s3.url', 'https://staging.aktivhub.app/minio/aktiv-media');

    Log::spy();

    $disk = Mockery::mock();
    $disk->shouldReceive('put')->once()->andReturn(false);

    Storage::shouldReceive('disk')
        ->with('s3')
        ->andReturn($disk);

    $service = new ImageUploadService();
    $file = UploadedFile::fake()->image('avatar.jpg', 300, 300);

    expect(fn () => $service->uploadAvatar($file))
        ->toThrow(\RuntimeException::class, 'Failed to upload image to object storage');

    Log::shouldHaveReceived('error')
        ->once()
        ->with(
            'Image upload failed while writing to object storage.',
            \Mockery::subset([
                'disk' => 's3',
                'bucket' => 'aktiv-media',
                'endpoint' => 'http://minio:9000',
                'url' => 'https://staging.aktivhub.app/minio/aktiv-media',
            ])
        );
});
