<?php

namespace App\Http\Controllers\Api;

use App\Enums\HubOwnerRequestStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\HubOwnerRequest\StoreHubOwnerRequest;
use App\Http\Resources\HubOwnerRequestResource;
use App\Mail\HubOwnerApplicationSubmitted;
use App\Models\HubOwnerRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class HubOwnerRequestController extends Controller
{
    public function show(): JsonResponse
    {
        $request = auth()->user()
            ->hubOwnerRequests()
            ->latest()
            ->first();

        return response()->json([
            'data' => $request ? new HubOwnerRequestResource($request) : null,
        ]);
    }

    public function store(StoreHubOwnerRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user->role !== UserRole::User, 403, 'Only regular users can apply to be a hub owner.');

        $latestRequest = $user->hubOwnerRequests()
            ->latest()
            ->first();

        abort_if(
            $latestRequest?->status === HubOwnerRequestStatus::Pending,
            409,
            'You already have a hub owner request pending review.'
        );

        abort_if(
            $latestRequest?->status === HubOwnerRequestStatus::Rejected,
            409,
            'Your previous hub owner request has already been reviewed.'
        );

        $hubOwnerRequest = HubOwnerRequest::query()->create([
            'user_id' => $user->id,
            'status' => HubOwnerRequestStatus::Pending,
            'hub_name' => $request->validated('hub_name'),
            'city' => $request->validated('city'),
            'contact_number' => $request->validated('contact_number'),
            'message' => $request->validated('message'),
        ])->load('user');

        User::query()
            ->where('role', UserRole::SuperAdmin)
            ->pluck('email')
            ->each(fn (string $email) => Mail::to($email)->queue(new HubOwnerApplicationSubmitted($hubOwnerRequest)));

        return response()->json([
            'message' => 'Hub owner application submitted successfully.',
            'data' => new HubOwnerRequestResource($hubOwnerRequest),
        ], 201);
    }
}
