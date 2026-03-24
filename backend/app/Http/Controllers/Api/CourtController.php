<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Court\StoreCourtRequest;
use App\Http\Requests\Court\UpdateCourtRequest;
use App\Models\Court;
use App\Models\CourtSport;
use App\Models\Hub;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;

class CourtController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService)
    {
    }
    /**
     * List all courts for a hub (public).
     */
    public function index(Hub $hub): JsonResponse
    {
        $courts = $hub->courts()
            ->with('sports')
            ->orderBy('name')
            ->get()
            ->map(fn (Court $court) => $this->formatCourt($court));

        return response()->json(['data' => $courts]);
    }

    /**
     * Create a court for a hub (owner only).
     */
    public function store(StoreCourtRequest $request, Hub $hub): JsonResponse
    {
        $this->authorize('create', [Court::class, $hub]);

        $validated = $request->validated();
        $sports = $validated['sports'] ?? [];
        unset($validated['sports']);
        unset($validated['court_image']);

        if ($request->hasFile('court_image')) {
            $uploaded = $this->imageUploadService->upload($request->file('court_image'), 'courts');
            $validated['image_path'] = $uploaded['path'];
            $validated['image_url']  = $uploaded['url'];
        }

        $court = $hub->courts()->create($validated);

        $this->syncCourtSports($court, $sports);
        HubController::resyncHubSportsFromCourts($hub);

        $court->load('sports');

        return response()->json(['data' => $this->formatCourt($court)], 201);
    }

    /**
     * Update a court (owner only).
     */
    public function update(UpdateCourtRequest $request, Hub $hub, Court $court): JsonResponse
    {
        $this->authorize('update', $court);

        $validated = $request->validated();
        $sports = isset($validated['sports']) ? $validated['sports'] : null;
        unset($validated['sports']);
        $removeCourteImage = (bool) ($validated['remove_court_image'] ?? false);
        unset($validated['remove_court_image']);
        unset($validated['court_image']);

        if ($request->hasFile('court_image')) {
            $uploaded = $this->imageUploadService->upload($request->file('court_image'), 'courts');
            $validated['image_path'] = $uploaded['path'];
            $validated['image_url']  = $uploaded['url'];
        } elseif ($removeCourteImage) {
            $validated['image_path'] = null;
            $validated['image_url']  = null;
        }

        $court->update($validated);

        if ($sports !== null) {
            $this->syncCourtSports($court, $sports);
            HubController::resyncHubSportsFromCourts($hub);
        }

        $court->load('sports');

        return response()->json(['data' => $this->formatCourt($court)]);
    }

    /**
     * Delete a court (owner only).
     */
    public function destroy(Hub $hub, Court $court): JsonResponse
    {
        $this->authorize('delete', $court);

        $court->delete();

        HubController::resyncHubSportsFromCourts($hub);

        return response()->json(null, 204);
    }

    /**
     * Sync court_sports to the exact provided list.
     *
     * @param  list<string>  $sports
     */
    private function syncCourtSports(Court $court, array $sports): void
    {
        $court->sports()->delete();

        foreach (array_unique($sports) as $sport) {
            CourtSport::query()->create(['court_id' => $court->id, 'sport' => $sport]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCourt(Court $court): array
    {
        return [
            'id'                       => $court->id,
            'hub_id'                   => $court->hub_id,
            'name'                     => $court->name,
            'surface'                  => $court->surface,
            'indoor'                   => $court->indoor,
            'price_per_hour'           => $court->price_per_hour,
            'is_active'                => $court->is_active,
            'sports'                   => $court->sports ? $court->sports->pluck('sport')->values() : [],
            'image_url'                => $court->image_url,
            'created_at'               => $court->created_at,
        ];
    }
}
