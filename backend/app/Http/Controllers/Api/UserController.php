<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Models\Hub;
use App\Models\User;
use App\Models\UserHeart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Return the top 5 most-visited hubs for a public user profile.
     * Returns 403 if the user has hidden this section via privacy settings.
     */
    public function mostVisitedHubs(User $user): JsonResponse
    {
        abort_if($user->isPendingDeletion(), 404);
        abort_unless($user->resolvedPrivacy()['show_visited_hubs'], 403);

        $rows = DB::table('bookings')
            ->select('courts.hub_id', DB::raw('COUNT(*) as visit_count'))
            ->join('courts', 'courts.id', '=', 'bookings.court_id')
            ->where('bookings.booked_by', $user->id)
            ->whereIn('bookings.status', ['confirmed', 'completed'])
            ->groupBy('courts.hub_id')
            ->orderByDesc('visit_count')
            ->limit(3)
            ->get();

        $hubs = Hub::query()
            ->whereIn('id', $rows->pluck('hub_id'))
            ->get(['id', 'name', 'username', 'city', 'cover_image_url'])
            ->keyBy('id');

        $data = $rows->map(fn ($row) => [
            'id'              => $row->hub_id,
            'name'            => $hubs[$row->hub_id]->name,
            'username'        => $hubs[$row->hub_id]->username,
            'city'            => $hubs[$row->hub_id]->city,
            'cover_image_url' => $hubs[$row->hub_id]->cover_image_url,
            'visit_count'     => $row->visit_count,
        ]);

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        abort_if($user->isPendingDeletion(), 404);

        return response()->json(['data' => new PublicUserResource($user)]);
    }

    public function resolveUsername(string $username): JsonResponse
    {
        $user = User::query()
            ->whereNull('deletion_scheduled_at')
            ->where('username', $username)
            ->firstOrFail();

        return response()->json(['data' => ['id' => $user->id]]);
    }

    public function toggleHeart(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if ($authUser->id === $user->id) {
            return response()->json(['message' => 'You cannot heart yourself.'], 422);
        }

        $existing = UserHeart::query()
            ->where('from_user_id', $authUser->id)
            ->where('to_user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $hearted = false;
        } else {
            UserHeart::create([
                'from_user_id' => $authUser->id,
                'to_user_id'   => $user->id,
            ]);
            $hearted = true;
        }

        $count = $user->heartsReceived()->count();

        return response()->json([
            'data' => [
                'hearted'      => $hearted,
                'hearts_count' => $count,
            ],
        ]);
    }
}
