<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HubRatingResource;
use App\Models\Hub;
use App\Models\HubRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class HubRatingController extends Controller
{
    /**
     * Public paginated list of ratings for a hub.
     * Supports ?sort=newest|highest|lowest (default: newest)
     */
    public function index(Hub $hub, Request $request): AnonymousResourceCollection
    {
        $query = $hub->ratings()
            ->with(['user:id,name,avatar_url', 'booking.court:id,name']);

        if ($request->filled('court')) {
            $query->whereHas('booking.court', fn ($q) =>
                $q->where('name', $request->string('court')->value())
            );
        }

        match ($request->string('sort')->value()) {
            'highest' => $query->orderByDesc('rating')->orderByDesc('created_at'),
            'lowest'  => $query->orderBy('rating')->orderByDesc('created_at'),
            default   => $query->orderByDesc('created_at'),
        };

        return HubRatingResource::collection($query->cursorPaginate(15));
    }

    /**
     * Return distinct court names that have ratings for this hub.
     */
    public function courts(Hub $hub): JsonResponse
    {
        $courts = DB::table('hub_ratings')
            ->join('bookings', 'hub_ratings.booking_id', '=', 'bookings.id')
            ->join('courts', 'bookings.court_id', '=', 'courts.id')
            ->where('hub_ratings.hub_id', $hub->id)
            ->whereNotNull('hub_ratings.booking_id')
            ->distinct()
            ->orderBy('courts.name')
            ->pluck('courts.name');

        return response()->json(['data' => $courts]);
    }

    /**
     * Create or update the authenticated user's rating for a hub.
     */
    public function store(Hub $hub, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
            'comment'    => ['nullable', 'string', 'max:1000'],
            'booking_id' => ['nullable', 'uuid', 'exists:bookings,id'],
        ]);

        if (isset($validated['booking_id'])) {
            abort_if(
                ! \App\Models\Booking::query()
                    ->where('id', $validated['booking_id'])
                    ->where('booked_by', $request->user()->id)
                    ->exists(),
                403,
                'This booking does not belong to you.'
            );
        }

        $rating = HubRating::query()->updateOrCreate(
            [
                'hub_id'  => $hub->id,
                'user_id' => $request->user()->id,
            ],
            [
                'rating'     => $validated['rating'],
                'comment'    => $validated['comment'] ?? null,
                'booking_id' => $validated['booking_id'] ?? null,
            ]
        );

        $rating->load(['user:id,name,avatar_url', 'booking.court:id,name']);

        return response()->json(['data' => new HubRatingResource($rating)], 201);
    }
}
