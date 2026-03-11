<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hub\StoreHubRequest;
use App\Http\Requests\Hub\UpdateHubRequest;
use App\Models\Hub;
use App\Models\HubContactNumber;
use App\Models\HubImage;
use App\Models\HubSport;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class HubController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService)
    {
    }

    /**
     * Public list of all approved hubs with aggregated data.
     */
    public function index(): JsonResponse
    {
        $hubs = Hub::query()
            ->where('is_approved', true)
            ->where('is_active', true)
            ->with(['sports', 'images', 'contactNumbers'])
            ->withCount('courts')
            ->withMin('courts', 'price_per_hour')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Hub $hub) => $this->formatHub($hub));

        return response()->json(['data' => $hubs]);
    }

    /**
     * List of hubs owned by the authenticated user.
     */
    public function myHubs(Request $request): JsonResponse
    {
        $hubs = Hub::query()
            ->where('owner_id', $request->user()->id)
            ->with(['sports', 'courts.sports', 'images', 'contactNumbers'])
            ->withCount('courts')
            ->withMin('courts', 'price_per_hour')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Hub $hub) => $this->formatHub($hub));

        return response()->json(['data' => $hubs]);
    }

    /**
     * Show a single hub with courts and sports.
     */
    public function show(Request $request, Hub $hub): JsonResponse
    {
        if (!$hub->is_active && auth('sanctum')->id() !== $hub->owner_id) {
            abort(404);
        }

        $hub->load(['sports', 'courts.sports', 'owner:id,name,avatar_url', 'images', 'contactNumbers']);
        $hub->loadCount('courts');
        $hub->loadAggregate('courts', 'min(price_per_hour)');

        return response()->json(['data' => $this->formatHub($hub)]);
    }

    /**
     * Create a new hub for the authenticated user.
     */
    public function store(StoreHubRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sports = $validated['sports'] ?? [];
        $contactNumbers = $validated['contact_numbers'] ?? [];
        $galleryImages = $request->file('gallery_images', []);
        unset($validated['sports'], $validated['contact_numbers']);
        unset($validated['cover_image'], $validated['gallery_images']);

        if ($request->hasFile('cover_image')) {
            $coverImage = $this->imageUploadService->upload($request->file('cover_image'), 'hubs/covers');
            $validated['cover_image_url'] = $coverImage['url'];
            $validated['cover_image_path'] = $coverImage['path'];
        }

        $hub = Hub::query()->create([
            ...$validated,
            'owner_id'    => $request->user()->id,
            'is_active'   => isset($validated['is_active']) ? (bool) $validated['is_active'] : true,
            'is_approved' => true,
            'is_verified' => false,
        ]);

        $this->syncHubSports($hub, $sports);
        $this->syncContactNumbers($hub, $contactNumbers);
        $this->uploadGalleryImages($hub, $galleryImages);

        $hub->load(['sports', 'images', 'contactNumbers']);

        return response()->json(['data' => $this->formatHub($hub)], 201);
    }

    /**
     * Update an existing hub (owner only).
     */
    public function update(UpdateHubRequest $request, Hub $hub): JsonResponse
    {
        $this->authorize('update', $hub);

        $validated = $request->validated();
        $sports = isset($validated['sports']) ? $validated['sports'] : null;
        $contactNumbers = array_key_exists('contact_numbers', $validated) ? $validated['contact_numbers'] : null;
        $galleryImages = $request->file('gallery_images', []);
        $removeGalleryImageIds = $validated['remove_gallery_image_ids'] ?? [];
        unset($validated['sports'], $validated['contact_numbers']);
        unset($validated['remove_gallery_image_ids']);
        unset($validated['cover_image'], $validated['gallery_images']);

        if ($request->hasFile('cover_image')) {
            $coverImage = $this->imageUploadService->upload($request->file('cover_image'), 'hubs/covers');

            if ($hub->cover_image_path) {
                Storage::disk('s3')->delete($hub->cover_image_path);
            }

            $validated['cover_image_url'] = $coverImage['url'];
            $validated['cover_image_path'] = $coverImage['path'];
        }

        if (!empty($removeGalleryImageIds)) {
            $imagesToRemove = $hub->images()->whereIn('id', $removeGalleryImageIds)->get();
            $this->removeGalleryImages($imagesToRemove);
        }

        $remainingGalleryCount = $hub->images()->count();
        if (($remainingGalleryCount + count($galleryImages)) > 10) {
            return response()->json([
                'message' => 'A hub can only have up to 10 gallery images.',
                'errors' => ['gallery_images' => ['A hub can only have up to 10 gallery images.']],
            ], 422);
        }

        $hub->update($validated);

        $this->uploadGalleryImages($hub, $galleryImages);

        if ($sports !== null) {
            $this->syncHubSports($hub, $sports);
        }

        if ($contactNumbers !== null) {
            $this->syncContactNumbers($hub, $contactNumbers);
        }

        $hub->load(['sports', 'courts.sports', 'images', 'contactNumbers']);
        $hub->loadCount('courts');
        $hub->loadAggregate('courts', 'min(price_per_hour)');

        return response()->json(['data' => $this->formatHub($hub)]);
    }

    /**
     * Delete a hub (owner only).
     */
    public function destroy(Hub $hub): JsonResponse
    {
        $this->authorize('delete', $hub);

        if ($hub->cover_image_path) {
            Storage::disk('s3')->delete($hub->cover_image_path);
        }

        $this->removeGalleryImages($hub->images()->get());

        $hub->delete();

        return response()->json(null, 204);
    }

    /**
     * Sync hub_sports to the exact provided list.
     *
     * @param  list<string>  $sports
     */
    private function syncHubSports(Hub $hub, array $sports): void
    {
        $hub->sports()->delete();

        foreach (array_unique($sports) as $sport) {
            HubSport::query()->create(['hub_id' => $hub->id, 'sport' => $sport]);
        }
    }

    /**
     * Sync hub_contact_numbers to the exact provided list.
     *
     * @param  list<array{type: string, number: string}>  $contactNumbers
     */
    private function syncContactNumbers(Hub $hub, array $contactNumbers): void
    {
        $hub->contactNumbers()->delete();

        foreach ($contactNumbers as $entry) {
            HubContactNumber::query()->create([
                'hub_id' => $hub->id,
                'type'   => $entry['type'],
                'number' => $entry['number'],
            ]);
        }
    }

    /**
     * @param array<int, \Illuminate\Http\UploadedFile> $galleryImages
     */
    private function uploadGalleryImages(Hub $hub, array $galleryImages): void
    {
        if (empty($galleryImages)) {
            return;
        }

        $currentOrder = (int) $hub->images()->max('order');

        foreach ($galleryImages as $file) {
            $currentOrder++;
            $uploaded = $this->imageUploadService->upload($file, 'hubs/gallery');

            HubImage::query()->create([
                'hub_id' => $hub->id,
                'storage_path' => $uploaded['path'],
                'url' => $uploaded['url'],
                'order' => $currentOrder,
            ]);
        }
    }

    /**
     * @param \Illuminate\Support\Collection<int, HubImage> $images
     */
    private function removeGalleryImages(Collection $images): void
    {
        foreach ($images as $image) {
            Storage::disk('s3')->delete($image->storage_path);
            $image->delete();
        }
    }

    /**
     * Derive and sync hub_sports from all court_sports for that hub.
     */
    public static function resyncHubSportsFromCourts(Hub $hub): void
    {
        $hub->load('courts.sports');

        $allSports = $hub->courts
            ->flatMap(fn ($court) => $court->sports->pluck('sport'))
            ->unique()
            ->values()
            ->all();

        $hub->sports()->delete();

        foreach ($allSports as $sport) {
            HubSport::query()->create(['hub_id' => $hub->id, 'sport' => $sport]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHub(Hub $hub): array
    {
        return [
            'id'                   => $hub->id,
            'name'                 => $hub->name,
            'description'          => $hub->description,
            'city'                 => $hub->city,
            'zip_code'             => $hub->zip_code,
            'province'             => $hub->province,
            'country'              => $hub->country,
            'address'              => $hub->address,
            'address_line2'        => $hub->address_line2,
            'landmark'             => $hub->landmark,
            'lat'                  => $hub->lat,
            'lng'                  => $hub->lng,
            'cover_image_url'      => $hub->cover_image_url,
            'gallery_images'       => $hub->images
                ? $hub->images->map(fn (HubImage $image): array => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'order' => $image->order,
                ])->values()
                : [],
            'is_active'            => $hub->is_active,
            'is_approved'          => $hub->is_approved,
            'is_verified'          => $hub->is_verified,
            'owner_id'             => $hub->owner_id,
            'owner'                => $hub->owner,
            'sports'               => $hub->sports ? $hub->sports->pluck('sport')->values() : [],
            'contact_numbers'      => $hub->contactNumbers
                ? $hub->contactNumbers->map(fn (HubContactNumber $c): array => [
                    'type'   => $c->type,
                    'number' => $c->number,
                ])->values()
                : [],
            'courts_count'         => $hub->courts_count ?? 0,
            'lowest_price_per_hour' => $hub->courts_min_price_per_hour,
            'created_at'           => $hub->created_at,
        ];
    }
}
