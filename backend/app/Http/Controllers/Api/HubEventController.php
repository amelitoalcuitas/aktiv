<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HubEvent\StoreHubEventRequest;
use App\Http\Requests\HubEvent\UpdateHubEventRequest;
use App\Http\Resources\HubEventResource;
use App\Models\Hub;
use App\Models\HubEvent;
use App\Support\HubTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HubEventController extends Controller
{
    /**
     * List active public events for a hub.
     */
    public function publicIndex(Hub $hub, Request $request): JsonResponse
    {
        if (!$hub->is_active && auth('sanctum')->id() !== $hub->owner_id) {
            abort(404);
        }

        $events = $this->buildHubEventsQuery($hub, $request)
            ->where('is_active', true)
            ->get();

        return response()->json(['data' => HubEventResource::collection($events)]);
    }

    /**
     * List all events for a hub (owner dashboard).
     */
    public function index(Hub $hub, Request $request): JsonResponse
    {
        $this->authorizeOwner($hub);

        $events = $this->buildHubEventsQuery($hub, $request)->get();

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

    private function buildHubEventsQuery(Hub $hub, Request $request)
    {
        $query = HubEvent::query()
            ->where('hub_id', $hub->id)
            ->orderByDesc('start_time');

        if ($request->filled('date_from')) {
            $query->where('end_time', '>=', HubTimezone::startOfDayUtc($request->string('date_from')->toString(), $hub->timezone_name));
        }

        if ($request->filled('date_to')) {
            $query->where('start_time', '<=', HubTimezone::endOfDayUtc($request->string('date_to')->toString(), $hub->timezone_name));
        }

        return $query;
    }
}
