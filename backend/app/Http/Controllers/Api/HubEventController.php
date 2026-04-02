<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HubEvent\StoreHubEventRequest;
use App\Http\Requests\HubEvent\UpdateHubEventRequest;
use App\Http\Resources\HubEventResource;
use App\Models\Hub;
use App\Models\HubEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HubEventController extends Controller
{
    /**
     * List all events for a hub (owner dashboard).
     */
    public function index(Hub $hub, Request $request): JsonResponse
    {
        $this->authorizeOwner($hub);

        $query = HubEvent::where('hub_id', $hub->id)
            ->orderByDesc('date_from');

        if ($request->filled('date_from')) {
            $query->where('date_to', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->where('date_from', '<=', $request->string('date_to')->toString());
        }

        $events = $query->get();

        return response()->json(['data' => HubEventResource::collection($events)]);
    }

    /**
     * Show a single event for the hub (owner dashboard).
     */
    public function show(Hub $hub, HubEvent $event): JsonResponse
    {
        $this->authorizeOwner($hub);
        abort_if($event->hub_id !== $hub->id, 404);

        return response()->json(['data' => new HubEventResource($event)]);
    }

    /**
     * Create a new event for the hub.
     */
    public function store(StoreHubEventRequest $request, Hub $hub): JsonResponse
    {
        $this->authorizeOwner($hub);

        $event = HubEvent::create([
            'hub_id' => $hub->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => new HubEventResource($event)], 201);
    }

    /**
     * Update an existing event.
     */
    public function update(UpdateHubEventRequest $request, Hub $hub, HubEvent $event): JsonResponse
    {
        $this->authorizeOwner($hub);
        abort_if($event->hub_id !== $hub->id, 404);

        $event->update($request->validated());

        return response()->json(['data' => new HubEventResource($event->fresh())]);
    }

    /**
     * Delete an event.
     */
    public function destroy(Hub $hub, HubEvent $event): JsonResponse
    {
        $this->authorizeOwner($hub);
        abort_if($event->hub_id !== $hub->id, 404);

        $event->delete();

        return response()->json(null, 204);
    }

    /**
     * Toggle the is_active flag on an event.
     */
    public function toggle(Hub $hub, HubEvent $event): JsonResponse
    {
        $this->authorizeOwner($hub);
        abort_if($event->hub_id !== $hub->id, 404);

        $event->update(['is_active' => ! $event->is_active]);

        return response()->json(['data' => new HubEventResource($event->fresh())]);
    }

    private function authorizeOwner(Hub $hub): void
    {
        abort_if(auth()->id() !== $hub->owner_id, 403);
    }
}
