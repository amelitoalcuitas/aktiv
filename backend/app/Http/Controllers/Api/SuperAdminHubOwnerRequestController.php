<?php

namespace App\Http\Controllers\Api;

use App\Enums\HubOwnerRequestStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\HubOwnerRequest\RejectHubOwnerRequest;
use App\Mail\HubOwnerApplicationApproved;
use App\Mail\HubOwnerApplicationRejected;
use App\Models\HubOwnerRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SuperAdminHubOwnerRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HubOwnerRequest::query()
            ->with(['user:id,first_name,last_name,email'])
            ->when(
                $request->filled('status'),
                fn ($builder) => $builder->where('status', $request->string('status')->value())
            )
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at');

        $items = $query->paginate(20)->through(fn (HubOwnerRequest $hubOwnerRequest) => $this->formatRequest($hubOwnerRequest));

        return response()->json($items);
    }

    public function approve(Request $request, HubOwnerRequest $hubOwnerRequest): JsonResponse
    {
        abort_if($hubOwnerRequest->status !== HubOwnerRequestStatus::Pending, 422, 'Only pending requests can be approved.');

        DB::transaction(function () use ($request, $hubOwnerRequest): void {
            $hubOwnerRequest->forceFill([
                'status' => HubOwnerRequestStatus::Approved,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            $hubOwnerRequest->user()->update([
                'role' => UserRole::Owner,
            ]);
        });

        $hubOwnerRequest->refresh()->load(['user', 'reviewer:id,first_name,last_name,email']);

        Mail::to($hubOwnerRequest->user->email)->queue(new HubOwnerApplicationApproved($hubOwnerRequest));

        return response()->json([
            'message' => 'Hub owner request approved.',
            'data' => $this->formatRequest($hubOwnerRequest),
        ]);
    }

    public function reject(RejectHubOwnerRequest $request, HubOwnerRequest $hubOwnerRequest): JsonResponse
    {
        abort_if($hubOwnerRequest->status !== HubOwnerRequestStatus::Pending, 422, 'Only pending requests can be rejected.');

        $hubOwnerRequest->forceFill([
            'status' => HubOwnerRequestStatus::Rejected,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $request->validated('review_notes'),
        ])->save();

        $hubOwnerRequest->refresh()->load(['user', 'reviewer:id,first_name,last_name,email']);

        Mail::to($hubOwnerRequest->user->email)->queue(new HubOwnerApplicationRejected($hubOwnerRequest));

        return response()->json([
            'message' => 'Hub owner request rejected.',
            'data' => $this->formatRequest($hubOwnerRequest),
        ]);
    }

    private function formatRequest(HubOwnerRequest $hubOwnerRequest): array
    {
        return [
            'id' => $hubOwnerRequest->id,
            'status' => $hubOwnerRequest->status->value,
            'hub_name' => $hubOwnerRequest->hub_name,
            'city' => $hubOwnerRequest->city,
            'contact_number' => $hubOwnerRequest->contact_number,
            'message' => $hubOwnerRequest->message,
            'review_notes' => $hubOwnerRequest->review_notes,
            'reviewed_at' => $hubOwnerRequest->reviewed_at?->toIso8601String(),
            'created_at' => $hubOwnerRequest->created_at?->toIso8601String(),
            'user' => [
                'id' => $hubOwnerRequest->user->id,
                'name' => $hubOwnerRequest->user->name,
                'email' => $hubOwnerRequest->user->email,
            ],
            'reviewer' => $hubOwnerRequest->reviewer instanceof User ? [
                'id' => $hubOwnerRequest->reviewer->id,
                'name' => $hubOwnerRequest->reviewer->name,
                'email' => $hubOwnerRequest->reviewer->email,
            ] : null,
        ];
    }
}
