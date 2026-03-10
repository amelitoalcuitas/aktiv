<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hub\StoreHubRequest;
use App\Http\Requests\Hub\UpdateHubRequest;
use App\Models\Hub;
use App\Models\HubSport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HubController extends Controller
{
    /**
     * Public list of all approved hubs with aggregated data.
     */
    public function index(): JsonResponse
    {
        $hubs = Hub::query()
            ->where('is_approved', true)
            ->with('sports')
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
            ->with(['sports', 'courts.sports'])
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
    public function show(Hub $hub): JsonResponse
    {
        $hub->load(['sports', 'courts.sports', 'owner:id,name,avatar_url']);
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
        unset($validated['sports']);

        $hub = Hub::query()->create([
            ...$validated,
            'owner_id'    => $request->user()->id,
            'is_approved' => true,
            'is_verified' => false,
        ]);

        $this->syncHubSports($hub, $sports);

        $hub->load('sports');

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
        unset($validated['sports']);

        $hub->update($validated);

        if ($sports !== null) {
            $this->syncHubSports($hub, $sports);
        }

        $hub->load(['sports', 'courts.sports']);
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
            'is_approved'          => $hub->is_approved,
            'is_verified'          => $hub->is_verified,
            'owner_id'             => $hub->owner_id,
            'owner'                => $hub->owner,
            'sports'               => $hub->sports ? $hub->sports->pluck('sport')->values() : [],
            'courts_count'         => $hub->courts_count ?? 0,
            'lowest_price_per_hour' => $hub->courts_min_price_per_hour,
            'created_at'           => $hub->created_at,
        ];
    }
}
