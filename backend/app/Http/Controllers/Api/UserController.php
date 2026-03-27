<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Models\User;
use App\Models\UserHeart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => new PublicUserResource($user)]);
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
