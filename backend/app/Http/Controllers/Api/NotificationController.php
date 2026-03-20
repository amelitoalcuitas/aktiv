<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * List the authenticated user's notifications, newest first.
     * Supports cursor pagination via ?cursor= and ?per_page= (default 25).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);

        $paginator = $request->user()
            ->notifications()
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->query('cursor'));

        $items = collect($paginator->items())->map(fn (DatabaseNotification $n) => $this->formatNotification($n));

        return response()->json([
            'data'        => $items,
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'has_more'    => $paginator->hasMorePages(),
        ]);
    }

    /**
     * Toggle a notification's read state.
     * Marks as read if currently unread; marks as unread if currently read.
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        } else {
            $notification->update(['read_at' => null]);
        }

        return response()->json(['data' => $this->formatNotification($notification->fresh())]);
    }

    /**
     * Mark all of the user's notifications as read.
     */
    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    // ── Private helpers ──────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function formatNotification(DatabaseNotification $notification): array
    {
        return [
            'id'            => $notification->id,
            'activity_type' => $notification->data['activity_type'] ?? null,
            'data'          => $notification->data,
            'read_at'       => $notification->read_at?->toIso8601String(),
            'created_at'    => $notification->created_at->toIso8601String(),
        ];
    }
}
