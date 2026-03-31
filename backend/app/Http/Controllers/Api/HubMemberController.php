<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HubMemberResource;
use App\Models\Hub;
use App\Models\HubMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HubMemberController extends Controller
{
    public function index(Hub $hub): JsonResponse
    {
        $activeMembers = $hub->members()->whereHas('user', fn ($q) => $q->whereNull('deletion_scheduled_at'));
        $total   = $activeMembers->count();
        $preview = $activeMembers
            ->with('user:id,first_name,last_name,username,avatar_thumb_url,profile_privacy,is_premium')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => HubMemberResource::collection($preview),
            'meta' => ['total' => $total],
        ]);
    }

    public function list(Hub $hub, Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 50), 50);

        return HubMemberResource::collection(
            $hub->members()
                ->whereHas('user', fn ($q) => $q->whereNull('deletion_scheduled_at'))
                ->with('user:id,first_name,last_name,username,avatar_thumb_url,profile_privacy,is_premium')
                ->cursorPaginate($perPage)
        );
    }

    public function join(Hub $hub, Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(
            $hub->members()->where('user_id', $user->id)->exists(),
            422,
            'You are already a member of this hub.'
        );

        $member = HubMember::create(['hub_id' => $hub->id, 'user_id' => $user->id]);
        $member->load('user:id,first_name,last_name,username,avatar_thumb_url,profile_privacy,is_premium');

        return response()->json(['data' => new HubMemberResource($member)], 201);
    }

    public function leave(Hub $hub, Request $request): JsonResponse
    {
        $deleted = $hub->members()->where('user_id', $request->user()->id)->delete();

        abort_if($deleted === 0, 422, 'You are not a member of this hub.');

        return response()->json(null, 204);
    }
}
