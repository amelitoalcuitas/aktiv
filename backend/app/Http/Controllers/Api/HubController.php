<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hub\StoreHubRequest;
use App\Http\Requests\Hub\UpdateHubRequest;
use App\Http\Resources\HubMemberResource;
use App\Models\Hub;
use App\Models\HubContactNumber;
use App\Models\HubEvent;
use App\Models\HubSettings;
use App\Models\HubImage;
use App\Models\HubWebsite;
use App\Models\HubSport;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class HubController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService)
    {
    }

    /**
     * Public list of approved hubs with optional filtering and pagination.
     *
     * Query params:
     *   search    – hub name LIKE search
     *   city      – exact city match (case-insensitive)
     *   sports[]  – filter by one or more sport slugs (hub must have at least one)
     *   sort      – "courts_count" (default) | "created_at"
     *   per_page  – results per page (default 12, max 48)
     *   page      – page number
     *   limit     – return at most N results without pagination (e.g. home page top-3)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Hub::query()
            ->where('is_approved', true)
            ->where('is_active', true)
            ->with(['sports', 'images', 'contactNumbers', 'websites', 'settings', 'operatingHours'])
            ->withCount('courts')
            ->withMin('courts', 'price_per_hour')
            ->withAvg('ratings', 'rating')
            ->withCount('ratings as reviews_count');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        if ($city = $request->string('city')->trim()->value()) {
            $query->where('city', 'ilike', "%{$city}%");
        }

        $sports = array_filter((array) $request->input('sports', []));
        if (!empty($sports)) {
            $query->whereHas('sports', fn ($q) => $q->whereIn('sport', $sports));
        }

        $lat = $request->filled('lat') ? (float) $request->input('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->input('lng') : null;

        if ($lat !== null && $lng !== null) {
            $haversine = '(6371 * acos(LEAST(1, cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))))';
            $radius = max(1, (int) ($request->input('radius') ?: 50));
            $query->whereRaw("{$haversine} < ?", [$lat, $lng, $lat, $radius]);
            $query->orderByRaw("{$haversine} ASC", [$lat, $lng, $lat]);
        } else {
            $sort = $request->string('sort')->trim()->value();
            if ($sort === 'courts_count') {
                $query->orderByDesc('courts_count');
            } else {
                $query->orderByDesc('created_at');
            }
        }

        $limit = $request->integer('limit');
        if ($limit > 0) {
            $hubs = $query->limit($limit)->get();
            $eventsMap = $this->loadActiveEventsMap($hubs->pluck('id')->all());

            return response()->json(['data' => $hubs->map(fn (Hub $hub) => $this->formatHub($hub, activeEvents: $eventsMap[$hub->id] ?? collect()))]);
        }

        $perPage = min((int) ($request->integer('per_page') ?: 12), 48);
        $paginator = $query->paginate($perPage);

        $suggestions = [];
        if ($search && $paginator->total() === 0) {
            $suggestions = $this->buildSuggestions($search, $request, $lat, $lng);
        }

        $eventsMap = $this->loadActiveEventsMap($paginator->getCollection()->pluck('id')->all());

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Hub $hub) => $this->formatHub($hub, activeEvents: $eventsMap[$hub->id] ?? collect())),
            'meta' => [
                'total'        => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
            ],
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * List of hubs owned by the authenticated user.
     */
    public function myHubs(Request $request): JsonResponse
    {
        $hubs = Hub::query()
            ->where('owner_id', $request->user()->id)
            ->with(['sports', 'courts.sports', 'images', 'contactNumbers', 'websites', 'settings'])
            ->withCount('courts')
            ->withMin('courts', 'price_per_hour')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Hub $hub) => $this->formatHub($hub));

        return response()->json(['data' => $hubs]);
    }

    /**
     * Show a single hub with courts and sports.
     */
    public function show(Request $request, Hub $hub): JsonResponse
    {
        if (!$hub->is_active && auth('sanctum')->id() !== $hub->owner_id) {
            abort(404);
        }

        $hub->load(['sports', 'courts.sports', 'owner:id,first_name,last_name,avatar_url', 'images', 'contactNumbers', 'websites', 'operatingHours', 'settings']);
        $hub->loadCount('courts');
        $hub->loadAggregate('courts', 'min(price_per_hour)');
        $hub->loadAvg('ratings', 'rating');
        $hub->loadCount('ratings as reviews_count');
        foreach ([1, 2, 3, 4, 5] as $star) {
            $hub->loadCount(["ratings as ratings_{$star}" => fn ($q) => $q->where('rating', $star)]);
        }

        $todayStart = now('Asia/Manila')->startOfDay();
        $todayEnd   = now('Asia/Manila')->endOfDay();
        $activeEvents = HubEvent::where('hub_id', $hub->id)
            ->where('is_active', true)
            ->where('date_from', '<=', $todayEnd)
            ->where('date_to', '>=', $todayStart)
            ->get();

        $membersCount = $hub->members()->count();
        $memberPreview = $hub->members()
            ->with('user:id,first_name,last_name,username,avatar_thumb_url')
            ->limit(5)
            ->get();
        $isMember = auth('sanctum')->id()
            ? $hub->members()->where('user_id', auth('sanctum')->id())->exists()
            : false;

        return response()->json(['data' => $this->formatHub(
            $hub,
            withBreakdown: true,
            activeEvents: $activeEvents,
            membersCount: $membersCount,
            memberPreview: $memberPreview,
            isMember: $isMember,
        )]);
    }

    /**
     * Create a new hub for the authenticated user.
     */
    public function store(StoreHubRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sports = $validated['sports'] ?? [];
        $contactNumbers = $validated['contact_numbers'] ?? [];
        $websites = $validated['websites'] ?? [];
        $operatingHours = $validated['operating_hours'] ?? null;
        $galleryImages = $request->file('gallery_images', []);
        unset($validated['sports'], $validated['contact_numbers'], $validated['websites']);
        unset($validated['cover_image'], $validated['gallery_images'], $validated['operating_hours']);
        unset($validated['payment_qr_image']);

        if ($request->hasFile('cover_image')) {
            $coverImage = $this->imageUploadService->upload($request->file('cover_image'), 'hubs/covers');
            $validated['cover_image_url'] = $coverImage['url'];
            $validated['cover_image_path'] = $coverImage['path'];
        }

        $paymentQrUrl = null;
        if ($request->hasFile('payment_qr_image')) {
            $paymentQr = $this->imageUploadService->upload($request->file('payment_qr_image'), 'hubs/payment-qr');
            $paymentQrUrl = $paymentQr['url'];
        }

        $settingsData = [
            'require_account_to_book' => isset($validated['require_account_to_book']) ? (bool) $validated['require_account_to_book'] : true,
            'payment_methods'         => $validated['payment_methods'] ?? ['pay_on_site'],
            'payment_qr_url'          => $paymentQrUrl,
            'digital_bank_name'       => $validated['digital_bank_name'] ?? null,
            'digital_bank_account'    => $validated['digital_bank_account'] ?? null,
        ];
        unset(
            $validated['require_account_to_book'],
            $validated['payment_methods'],
            $validated['digital_bank_name'],
            $validated['digital_bank_account'],
        );

        $hub = Hub::query()->create([
            ...$validated,
            'owner_id'    => $request->user()->id,
            'is_active'   => isset($validated['is_active']) ? (bool) $validated['is_active'] : true,
            'is_approved' => true,
            'is_verified' => false,
        ]);

        HubSettings::query()->create(['hub_id' => $hub->id, ...$settingsData]);

        $this->syncHubSports($hub, $sports);
        $this->syncContactNumbers($hub, $contactNumbers);
        $this->syncWebsites($hub, $websites);
        $this->uploadGalleryImages($hub, $galleryImages);
        $this->syncOperatingHours($hub, $operatingHours);

        $hub->load(['sports', 'images', 'contactNumbers', 'websites', 'operatingHours', 'settings']);

        return response()->json(['data' => $this->formatHub($hub)], 201);
    }

    /**
     * Update an existing hub (owner only).
     */
    public function update(UpdateHubRequest $request, Hub $hub): JsonResponse
    {
        $this->authorize('update', $hub);

        $validated = $request->validated();
        $sports = isset($validated['sports']) ? $validated['sports'] : null;
        $contactNumbers = array_key_exists('contact_numbers', $validated) ? $validated['contact_numbers'] : null;
        $websites = array_key_exists('websites', $validated) ? $validated['websites'] : null;
        $operatingHours = array_key_exists('operating_hours', $validated) ? $validated['operating_hours'] : null;
        $galleryImages = $request->file('gallery_images', []);
        $removeGalleryImageIds = $validated['remove_gallery_image_ids'] ?? [];
        unset($validated['sports'], $validated['contact_numbers'], $validated['websites']);
        unset($validated['remove_gallery_image_ids'], $validated['operating_hours']);
        unset($validated['cover_image'], $validated['gallery_images']);
        unset($validated['payment_qr_image']);
        $removePaymentQr = (bool) ($validated['remove_payment_qr'] ?? false);
        unset($validated['remove_payment_qr']);

        $settingsData = [];
        foreach (['require_account_to_book', 'guest_booking_limit', 'guest_max_hours', 'payment_methods', 'digital_bank_name', 'digital_bank_account'] as $field) {
            if (array_key_exists($field, $validated)) {
                $settingsData[$field] = $validated[$field];
                unset($validated[$field]);
            }
        }
        if ($removePaymentQr && ! $request->hasFile('payment_qr_image')) {
            $settingsData['payment_qr_url'] = null;
        } elseif ($request->hasFile('payment_qr_image')) {
            $paymentQr = $this->imageUploadService->upload($request->file('payment_qr_image'), 'hubs/payment-qr');
            $settingsData['payment_qr_url'] = $paymentQr['url'];
        }

        if ($request->hasFile('cover_image')) {
            $coverImage = $this->imageUploadService->upload($request->file('cover_image'), 'hubs/covers');

            if ($hub->cover_image_path) {
                Storage::disk('s3')->delete($hub->cover_image_path);
            }

            $validated['cover_image_url'] = $coverImage['url'];
            $validated['cover_image_path'] = $coverImage['path'];
        }

        if (!empty($removeGalleryImageIds)) {
            $imagesToRemove = $hub->images()->whereIn('id', $removeGalleryImageIds)->get();
            $this->removeGalleryImages($imagesToRemove);
        }

        $remainingGalleryCount = $hub->images()->count();
        if (($remainingGalleryCount + count($galleryImages)) > 10) {
            return response()->json([
                'message' => 'A hub can only have up to 10 gallery images.',
                'errors' => ['gallery_images' => ['A hub can only have up to 10 gallery images.']],
            ], 422);
        }

        $hub->update($validated);

        if (! empty($settingsData)) {
            $hub->settings()->firstOrCreate(['hub_id' => $hub->id])->update($settingsData);
        }

        $this->uploadGalleryImages($hub, $galleryImages);

        if ($sports !== null) {
            $this->syncHubSports($hub, $sports);
        }

        if ($contactNumbers !== null) {
            $this->syncContactNumbers($hub, $contactNumbers);
        }

        if ($websites !== null) {
            $this->syncWebsites($hub, $websites);
        }

        if ($operatingHours !== null) {
            $this->syncOperatingHours($hub, $operatingHours);
        }

        $hub->load(['sports', 'courts.sports', 'images', 'contactNumbers', 'websites', 'operatingHours', 'settings']);
        $hub->loadCount('courts');
        $hub->loadAggregate('courts', 'min(price_per_hour)');

        return response()->json(['data' => $this->formatHub($hub)]);
    }

    /**
     * Delete a hub (owner only).
     */
    public function destroy(Hub $hub): JsonResponse
    {
        $this->authorize('delete', $hub);

        if ($hub->cover_image_path) {
            Storage::disk('s3')->delete($hub->cover_image_path);
        }

        $this->removeGalleryImages($hub->images()->get());

        $hub->delete();

        return response()->json(null, 204);
    }

    public function updateShowOnProfile(Request $request, Hub $hub): JsonResponse
    {
        $this->authorize('update', $hub);

        $request->validate(['show_on_profile' => ['required', 'boolean']]);

        $hub->update(['show_on_profile' => $request->boolean('show_on_profile')]);

        return response()->json(['data' => ['id' => $hub->id, 'show_on_profile' => $hub->show_on_profile]]);
    }

    /**
     * Sync hub_sports to the exact provided list.
     *
     * @param  list<string>  $sports
     */
    private function syncHubSports(Hub $hub, array $sports): void
    {
        $hub->sports()->delete();

        foreach (array_unique($sports) as $sport) {
            HubSport::query()->create(['hub_id' => $hub->id, 'sport' => $sport]);
        }
    }

    /**
     * Sync hub_websites to the exact provided list.
     *
     * @param  list<array{url: string}>  $websites
     */
    private function syncWebsites(Hub $hub, array $websites): void
    {
        $hub->websites()->delete();

        foreach ($websites as $entry) {
            HubWebsite::query()->create([
                'hub_id' => $hub->id,
                'url'    => $entry['url'],
            ]);
        }
    }

    /**
     * Sync hub_contact_numbers to the exact provided list.
     *
     * @param  list<array{type: string, number: string}>  $contactNumbers
     */
    private function syncContactNumbers(Hub $hub, array $contactNumbers): void
    {
        $hub->contactNumbers()->delete();

        foreach ($contactNumbers as $entry) {
            HubContactNumber::query()->create([
                'hub_id' => $hub->id,
                'type'   => $entry['type'],
                'number' => $entry['number'],
            ]);
        }
    }

    /**
     * @param array<int, \Illuminate\Http\UploadedFile> $galleryImages
     */
    private function uploadGalleryImages(Hub $hub, array $galleryImages): void
    {
        if (empty($galleryImages)) {
            return;
        }

        $currentOrder = (int) $hub->images()->max('order');

        foreach ($galleryImages as $file) {
            $currentOrder++;
            $uploaded = $this->imageUploadService->upload($file, 'hubs/gallery');

            HubImage::query()->create([
                'hub_id' => $hub->id,
                'storage_path' => $uploaded['path'],
                'url' => $uploaded['url'],
                'order' => $currentOrder,
            ]);
        }
    }

    /**
     * @param \Illuminate\Support\Collection<int, HubImage> $images
     */
    private function removeGalleryImages(Collection $images): void
    {
        foreach ($images as $image) {
            Storage::disk('s3')->delete($image->storage_path);
            $image->delete();
        }
    }

    /**
     * Upsert operating hours for the hub.
     *
     * @param  list<array{day_of_week: int, opens_at: string|null, closes_at: string|null, is_closed: bool}>|null  $hours
     */
    private function syncOperatingHours(Hub $hub, ?array $hours): void
    {
        if ($hours === null) {
            return;
        }

        foreach ($hours as $oh) {
            $hub->operatingHours()->updateOrCreate(
                ['day_of_week' => (int) $oh['day_of_week']],
                [
                    'opens_at'  => $oh['opens_at'] ?? null,
                    'closes_at' => $oh['closes_at'] ?? null,
                    'is_closed' => (bool) ($oh['is_closed'] ?? false),
                ]
            );
        }
    }

    /**
     * Derive and sync hub_sports from all court_sports for that hub.
     */
    public static function resyncHubSportsFromCourts(Hub $hub): void
    {
        $hub->load('courts.sports');

        $allSports = $hub->courts
            ->flatMap(fn ($court) => $court->sports->pluck('sport'))
            ->unique()
            ->values()
            ->all();

        $hub->sports()->delete();

        foreach ($allSports as $sport) {
            HubSport::query()->create(['hub_id' => $hub->id, 'sport' => $sport]);
        }
    }

    /**
     * Build up to 5 fuzzy suggestions when an exact search yields zero results.
     *
     * Uses pg_trgm word_similarity() for name/city matching, exact sport slug matching,
     * and optionally proximity when the user's coordinates are available.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSuggestions(string $search, Request $request, ?float $lat, ?float $lng): array
    {
        $knownSports = ['badminton', 'tennis', 'pickleball', 'basketball', 'volleyball'];
        $words = array_filter(preg_split('/\s+/', strtolower(trim($search))) ?: []);
        $sportWords = array_values(array_intersect($words, $knownSports));

        $suggestionQuery = Hub::query()
            ->where('is_approved', true)
            ->where('is_active', true)
            ->with(['sports', 'images', 'contactNumbers', 'websites', 'settings', 'operatingHours'])
            ->withCount('courts')
            ->withMin('courts', 'price_per_hour')
            ->withAvg('ratings', 'rating')
            ->withCount('ratings as reviews_count');

        // Respect any city/sports filters the user already applied
        if ($city = $request->string('city')->trim()->value()) {
            $suggestionQuery->where('city', 'ilike', "%{$city}%");
        }
        $appliedSports = array_filter((array) $request->input('sports', []));
        if (! empty($appliedSports)) {
            $suggestionQuery->whereHas('sports', fn ($q) => $q->whereIn('sport', $appliedSports));
        }

        $haversine = '(6371 * acos(LEAST(1, cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))))';

        $suggestionQuery->where(function ($q) use ($search, $sportWords, $lat, $lng, $haversine): void {
            $q->whereRaw('word_similarity(?, name) > 0.25', [$search])
              ->orWhereRaw('word_similarity(?, city) > 0.25', [$search]);

            if (! empty($sportWords)) {
                $q->orWhereHas('sports', fn ($sq) => $sq->whereIn('sport', $sportWords));
            }

            if ($lat !== null && $lng !== null) {
                $q->orWhereRaw("{$haversine} < 50", [$lat, $lng, $lat]);
            }
        });

        // Order: best trigram similarity first; proximity as tiebreaker when available
        if ($lat !== null && $lng !== null) {
            $suggestionQuery->orderByRaw(
                'GREATEST(word_similarity(?, name), word_similarity(?, city)) DESC, ' . $haversine . ' ASC',
                [$search, $search, $lat, $lng, $lat]
            );
        } else {
            $suggestionQuery->orderByRaw(
                'GREATEST(word_similarity(?, name), word_similarity(?, city)) DESC',
                [$search, $search]
            );
        }

        return $suggestionQuery
            ->limit(5)
            ->get()
            ->map(fn (Hub $hub) => $this->formatHub($hub))
            ->values()
            ->all();
    }

    /**
     * Bayesian average rating: pulls toward 3.5 prior when review count is low.
     * C=5 means a hub needs ~5 reviews before its own average dominates.
     */
    private function bayesianRating(mixed $avg, int $count): ?float
    {
        if ($count === 0 || $avg === null) {
            return null;
        }

        $C     = 5;
        $prior = 3.5;
        $sum   = (float) $avg * $count;

        return round(($C * $prior + $sum) / ($C + $count), 1);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Load active events for a list of hub IDs, grouped by hub_id.
     *
     * @param  array<string>  $hubIds
     * @return array<string, \Illuminate\Support\Collection<int, HubEvent>>
     */
    private function loadActiveEventsMap(array $hubIds): array
    {
        if (empty($hubIds)) {
            return [];
        }

        $todayStart = now('Asia/Manila')->startOfDay();
        $todayEnd   = now('Asia/Manila')->endOfDay();

        return HubEvent::whereIn('hub_id', $hubIds)
            ->where('is_active', true)
            ->where('date_from', '<=', $todayEnd)
            ->where('date_to', '>=', $todayStart)
            ->get()
            ->groupBy('hub_id')
            ->all();
    }

    private function formatHub(Hub $hub, bool $withBreakdown = false, ?\Illuminate\Support\Collection $activeEvents = null, int $membersCount = 0, ?\Illuminate\Support\Collection $memberPreview = null, bool $isMember = false): array
    {
        return [
            'id'                   => $hub->id,
            'name'                 => $hub->name,
            'description'          => $hub->description,
            'city'                 => $hub->city,
            'zip_code'             => $hub->zip_code,
            'province'             => $hub->province,
            'country'              => $hub->country,
            'address'              => $hub->address,
            'address_line2'        => $hub->address_line2,
            'landmark'             => $hub->landmark,
            'lat'                  => $hub->lat,
            'lng'                  => $hub->lng,
            'cover_image_url'      => $hub->cover_image_url,
            'gallery_images'       => $hub->images
                ? $hub->images->map(fn (HubImage $image): array => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'order' => $image->order,
                ])->values()
                : [],
            'is_active'               => $hub->is_active,
            'is_approved'             => $hub->is_approved,
            'is_verified'             => $hub->is_verified,
            'require_account_to_book' => $hub->settings?->require_account_to_book ?? true,
            'guest_booking_limit'     => $hub->settings?->guest_booking_limit ?? 1,
            'guest_max_hours'         => $hub->settings?->guest_max_hours ?? 2,
            'payment_methods'         => $hub->settings?->payment_methods ?? ['pay_on_site'],
            'payment_qr_url'          => $hub->settings?->payment_qr_url,
            'digital_bank_name'       => $hub->settings?->digital_bank_name,
            'digital_bank_account'    => $hub->settings?->digital_bank_account,
            'owner_id'             => $hub->owner_id,
            'owner'                => $hub->owner,
            'sports'               => $hub->sports ? $hub->sports->pluck('sport')->values() : [],
            'contact_numbers'      => $hub->contactNumbers
                ? $hub->contactNumbers->map(fn (HubContactNumber $c): array => [
                    'type'   => $c->type,
                    'number' => $c->number,
                ])->values()
                : [],
            'websites'             => $hub->websites
                ? $hub->websites->map(fn (HubWebsite $w): array => ['url' => $w->url])->values()
                : [],
            'courts_count'         => $hub->courts_count ?? 0,
            'lowest_price_per_hour' => $hub->courts_min_price_per_hour,
            'rating'               => $this->bayesianRating($hub->ratings_avg_rating, $hub->reviews_count ?? 0),
            'reviews_count'        => $hub->reviews_count ?? 0,
            'rating_breakdown'     => $withBreakdown ? [
                5 => (int) ($hub->ratings_5 ?? 0),
                4 => (int) ($hub->ratings_4 ?? 0),
                3 => (int) ($hub->ratings_3 ?? 0),
                2 => (int) ($hub->ratings_2 ?? 0),
                1 => (int) ($hub->ratings_1 ?? 0),
            ] : null,
            'operating_hours'      => $hub->operatingHours
                ? $hub->operatingHours->map(fn ($oh): array => [
                    'day_of_week' => $oh->day_of_week,
                    'opens_at'    => $oh->opens_at,
                    'closes_at'   => $oh->closes_at,
                    'is_closed'   => $oh->is_closed,
                ])->values()
                : [],
            'created_at'           => $hub->created_at,
            'has_active_promo'         => $activeEvents
                ? $activeEvents->contains('event_type', 'promo')
                : false,
            'has_active_announcement'  => $activeEvents
                ? $activeEvents->contains('event_type', 'announcement')
                : false,
            'members_count'  => $membersCount,
            'member_preview' => $memberPreview !== null
                ? HubMemberResource::collection($memberPreview)->resolve()
                : [],
            'is_member'      => $isMember,
            'active_events'            => $withBreakdown && $activeEvents !== null
                ? $activeEvents->values()->map(fn (HubEvent $e): array => [
                    'id'               => $e->id,
                    'title'            => $e->title,
                    'description'      => $e->description,
                    'event_type'       => $e->event_type,
                    'date_from'        => $e->date_from->toDateString(),
                    'date_to'          => $e->date_to->toDateString(),
                    'time_from'        => $e->time_from,
                    'time_to'          => $e->time_to,
                    'discount_type'    => $e->discount_type,
                    'discount_value'   => $e->discount_value,
                    'affected_courts'  => $e->affected_courts,
                    'court_discounts'  => $e->court_discounts,
                ])
                : null,
        ];
    }
}
