<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MyBookingItemResource;
use App\Http\Resources\UserBookingResource;
use App\Models\Booking;
use App\Models\BookingReviewSkip;
use App\Models\OpenPlayParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

class UserBookingController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * List the authenticated user's bookings, newest first.
     * Supports optional filter: ?status=pending_payment (or any BookingStatus value)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = $this->paginateItems(
            $this->buildMyBookingItems($request),
            (int) $request->integer('page', 1),
        );

        return MyBookingItemResource::collection($items);
    }

    /**
     * Return the page number (1-indexed, 10/page) where a specific booking appears
     * in the user's full booking list (ordered by start_time DESC).
     */
    public function pageOf(Request $request): JsonResponse
    {
        $request->validate([
            'item_id'    => ['nullable', 'uuid'],
            'booking_id' => ['nullable', 'uuid'],
        ]);

        $itemId = $request->string('item_id')->toString() ?: $request->string('booking_id')->toString();
        abort_if($itemId === '', 422, 'The item_id field is required.');

        $position = $this->buildMyBookingItems($request)
            ->values()
            ->search(fn ($item) => $item->id === $itemId);

        abort_if($position === false, 404);

        $page = (int) floor($position / self::PER_PAGE) + 1;

        return response()->json(['page' => $page]);
    }

    /**
     * Return all unreviewed ended bookings for the authenticated user (one per hub, most recent).
     *
     * For local testing, pass ?test_booking_id=<id> to force a specific booking.
     */
    public function pendingReview(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Dev shortcut: force a specific booking for testing
        if (app()->environment('local') && $request->filled('test_booking_id')) {
            $booking = Booking::query()
                ->where('id', $request->string('test_booking_id'))
                ->where('booked_by', $userId)
                ->whereIn('status', ['confirmed', 'completed'])
                ->whereDoesntHave('court.hub.ratings', fn ($q) => $q->where('user_id', $userId))
                ->whereDoesntHave('reviewSkip', fn ($q) => $q->where('user_id', $userId))
                ->with(['court:id,name,hub_id', 'court.hub:id,name,username,cover_image_url'])
                ->first();

            return response()->json(['bookings' => $booking ? UserBookingResource::collection(collect([$booking])) : []]);
        }

        $bookings = Booking::query()
            ->where('booked_by', $userId)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('end_time', '<', now())
            ->whereDoesntHave('court.hub.ratings', fn ($q) => $q->where('user_id', $userId))
            ->whereDoesntHave('reviewSkip', fn ($q) => $q->where('user_id', $userId))
            ->with(['court:id,name,hub_id', 'court.hub:id,name,username,cover_image_url'])
            ->orderByDesc('end_time')
            ->get()
            ->unique(fn ($b) => $b->court->hub_id)
            ->values();

        return response()->json(['bookings' => UserBookingResource::collection($bookings)]);
    }

    /**
     * Permanently skip the review prompt for a specific booking.
     */
    public function skipReview(Request $request): JsonResponse
    {
        $request->validate(['booking_id' => ['required', 'uuid', 'exists:bookings,id']]);

        BookingReviewSkip::query()->firstOrCreate([
            'user_id'    => $request->user()->id,
            'booking_id' => $request->booking_id,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Cancel the user's own booking (only if pending_payment or payment_sent).
     */
    public function cancel(Booking $booking, Request $request): JsonResponse
    {
        $booking = $this->cancelBookingModel($booking, $request->user()->id);

        return response()->json(['data' => new MyBookingItemResource($booking)]);
    }

    /**
     * Cancel a mixed "my bookings" entry.
     */
    public function cancelItem(string $entryType, string $entryId, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $item = match ($entryType) {
            'booking' => $this->cancelBookingModel(
                Booking::query()->whereKey($entryId)->firstOrFail(),
                $userId,
            ),
            'open_play_participant' => $this->cancelOpenPlayParticipantModel($entryId, $userId),
            default => abort(404),
        };

        return response()->json(['data' => new MyBookingItemResource($item)]);
    }

    private function cancelBookingModel(Booking $booking, string $userId): Booking
    {
        abort_if($booking->booked_by !== $userId, 403);

        abort_unless(
            in_array($booking->status, ['pending_payment', 'payment_sent'], true),
            422,
            'This booking cannot be cancelled.'
        );

        $booking->update([
            'status'       => 'cancelled',
            'cancelled_by' => 'user',
        ]);

        return $booking->load(['court:id,name,hub_id', 'court.hub:id,name,username,cover_image_url']);
    }

    private function cancelOpenPlayParticipantModel(string $entryId, string $userId): OpenPlayParticipant
    {
        $participant = OpenPlayParticipant::query()
            ->whereKey($entryId)
            ->where('user_id', $userId)
            ->with([
                'openPlaySession.booking.court.hub:id,name,username,cover_image_url',
            ])
            ->firstOrFail();

        abort_unless(
            in_array($participant->payment_status, ['pending_payment', 'payment_sent'], true),
            422,
            'This open play join cannot be cancelled.'
        );

        $participant->update([
            'payment_status' => 'cancelled',
            'cancelled_by'   => 'user',
        ]);

        $participant->load([
            'openPlaySession' => fn ($query) => $query
                ->with(['booking.court.hub:id,name,username,cover_image_url'])
                ->withCount([
                    'participants as participants_count' => fn ($participantQuery) => $participantQuery
                        ->where('payment_status', '!=', 'cancelled'),
                ]),
        ]);

        $participant->openPlaySession?->recalculateStatus();

        return $participant->fresh([
            'openPlaySession' => fn ($query) => $query
                ->with(['booking.court.hub:id,name,username,cover_image_url'])
                ->withCount([
                    'participants as participants_count' => fn ($participantQuery) => $participantQuery
                        ->where('payment_status', '!=', 'cancelled'),
                ]),
        ]);
    }

    private function buildMyBookingItems(Request $request): Collection
    {
        $userId = $request->user()->id;
        $status = $request->string('status')->toString();

        $bookings = Booking::query()
            ->where('booked_by', $userId)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->with(['court:id,name,hub_id', 'court.hub:id,name,username,cover_image_url'])
            ->get();

        $participants = OpenPlayParticipant::query()
            ->where('user_id', $userId)
            ->when($status !== '', fn ($query) => $query->where('payment_status', $status))
            ->with([
                'openPlaySession' => fn ($query) => $query
                    ->with(['booking.court.hub:id,name,username,cover_image_url'])
                    ->withCount([
                        'participants as participants_count' => fn ($participantQuery) => $participantQuery
                            ->where('payment_status', '!=', 'cancelled'),
                    ]),
            ])
            ->get();

        return $bookings
            ->concat($participants)
            ->sort(function ($left, $right): int {
                $createdComparison = $right->created_at->getTimestamp() <=> $left->created_at->getTimestamp();

                if ($createdComparison !== 0) {
                    return $createdComparison;
                }

                return strcmp($right->id, $left->id);
            })
            ->values();
    }

    private function paginateItems(Collection $items, int $page): LengthAwarePaginator
    {
        $page = max($page, 1);

        return new LengthAwarePaginator(
            $items->forPage($page, self::PER_PAGE)->values(),
            $items->count(),
            self::PER_PAGE,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }
}
