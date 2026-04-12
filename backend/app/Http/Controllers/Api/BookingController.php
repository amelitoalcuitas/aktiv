<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\PreviewVoucherRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\UploadReceiptRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Models\GuestBookingPenalty;
use App\Models\Hub;
use App\Services\BookingNotificationService;
use App\Services\HubEventDiscountService;
use App\Services\ImageUploadService;
use App\Support\HubTimezone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeFacade;

class BookingController extends Controller
{
    public function __construct(
        private BookingNotificationService $notifications,
        private HubEventDiscountService $discounts
    ) {}
    /**
     * List non-cancelled bookings for ALL courts in a hub in a single request.
     * Public — no auth required. Returns minimal data grouped by court_id.
     * Supports optional date_from / date_to query params (YYYY-MM-DD).
     */
    public function hubIndex(Request $request, Hub $hub): JsonResponse
    {
        $courtIds = $hub->courts()->pluck('id');

        $query = Booking::whereIn('court_id', $courtIds)
            ->whereNotIn('status', ['cancelled']);

        $timezone = $hub->timezone_name;

        if ($request->filled('date_from')) {
            $query->where('end_time', '>=', HubTimezone::startOfDayUtc($request->date_from, $timezone));
        } else {
            $query->where('end_time', '>=', HubTimezone::todayStartUtc($timezone));
        }

        if ($request->filled('date_to')) {
            $query->where('start_time', '<=', HubTimezone::endOfDayUtc($request->date_to, $timezone));
        }

        $bookings = $query->orderBy('start_time')->get();
        $userId = auth()->id();

        $grouped = [];
        foreach ($bookings as $b) {
            $grouped[$b->court_id][] = [
                'id' => $b->id,
                'start_time' => $b->start_time->toIso8601String(),
                'end_time' => $b->end_time->toIso8601String(),
                'session_type' => $b->session_type,
                'status' => $b->status,
                'is_own' => $userId !== null && (int) $b->booked_by === (int) $userId,
            ];
        }

        return response()->json(['data' => $grouped]);
    }

    /**
     * List non-cancelled upcoming bookings for a court (used to render the calendar).
     * Public — no auth required. Returns minimal data; no personal info exposed.
     */
    public function index(Hub $hub, Court $court): JsonResponse
    {
        abort_if($court->hub_id !== $hub->id, 404);

        $bookings = Booking::where('court_id', $court->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('end_time', '>=', HubTimezone::todayStartUtc($hub->timezone_name))
            ->orderBy('start_time')
            ->get();

        $userId = auth()->id();

        $data = $bookings->map(fn (Booking $b) => [
            'id' => $b->id,
            'start_time' => $b->start_time->toIso8601String(),
            'end_time' => $b->end_time->toIso8601String(),
            'session_type' => $b->session_type,
            'status' => $b->status,
            'is_own' => $userId !== null && (int) $b->booked_by === (int) $userId,
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Create a self-booked booking. Requires authentication.
     * Slot is immediately blocked as pending_payment with a 1-hour expiry.
     */
    public function store(StoreBookingRequest $request, Hub $hub, Court $court): JsonResponse
    {
        abort_if($court->hub_id !== $hub->id, 404);

        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email address is not verified.'], 403);
        }

        // Check if the user is booking-banned
        if ($user->isBookingBanned()) {
            return response()->json([
                'message' => 'Your account is temporarily restricted from making new bookings.',
                'banned_until' => $user->booking_banned_until->toIso8601String(),
            ], 403);
        }

        $startTime = Carbon::parse($request->start_time);
        $endTime = Carbon::parse($request->end_time);

        // Closure check: reject if an active closure event covers this court and time window
        $closureEvent = $this->discounts->findClosureEvent($hub, $court, $startTime, $endTime);
        if ($closureEvent) {
            return response()->json([
                'message' => "Court {$court->name} is unavailable because of the closure event \"{$closureEvent->title}\".",
            ], 422);
        }

        // Conflict detection: any non-cancelled, non-expired booking whose interval overlaps
        $conflict = Booking::where('court_id', $court->id)
            ->whereNotIn('status', ['cancelled'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'This time slot is already booked. Please choose a different time.',
            ], 409);
        }

        [$booking, $appliedDiscount] = DB::transaction(function () use ($hub, $court, $request, $startTime, $endTime) {
            $pricing = $this->discounts->resolveBookingPricing(
                hub: $hub,
                court: $court,
                startTime: $startTime,
                endTime: $endTime,
                voucherCode: $request->voucher_code,
                user: $request->user(),
                lockVoucher: true,
            );

            $appliedDiscount = $pricing['applied_discount'];
            $booking = Booking::create([
                'court_id'            => $court->id,
                'booked_by'           => $request->user()->id,
                'created_by'          => $request->user()->id,
                'sport'               => $court->sports->first()?->sport,
                'start_time'          => $startTime,
                'end_time'            => $endTime,
                'session_type'        => $request->session_type,
                'status'              => 'pending_payment',
                'booking_source'      => 'self_booked',
                'total_price'         => $pricing['total_price'],
                'original_price'      => $pricing['original_price'],
                'discount_amount'     => $pricing['discount_amount'],
                'applied_promo_title' => $appliedDiscount['label'] ?? null,
                'applied_hub_event_id'=> ($appliedDiscount['source'] ?? null) === 'voucher'
                    ? $appliedDiscount['event_id']
                    : null,
                'payment_method'      => $request->payment_method,
                'expires_at'          => $this->resolveExpiresAt($request->payment_method, $startTime),
            ]);

            return [$booking, $appliedDiscount];
        });

        $booking->load('court.hub.owner');
        $this->notifications->notifyNewBooking($booking);

        return response()->json([
            'message' => 'Booking created successfully.',
            'data' => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'court_id' => $booking->court_id,
                'sport' => $booking->sport,
                'start_time' => $booking->start_time->toIso8601String(),
                'end_time' => $booking->end_time->toIso8601String(),
                'hub_timezone' => $hub->timezone_name,
                'session_type' => $booking->session_type,
                'status' => $booking->status,
                'booking_source' => $booking->booking_source,
                'total_price' => $booking->total_price,
                'expires_at' => $booking->expires_at->toIso8601String(),
                'created_at' => $booking->created_at->toIso8601String(),
                'applied_discount' => $appliedDiscount,
            ],
        ], 201);
    }

    public function previewVoucher(PreviewVoucherRequest $request, Hub $hub): JsonResponse
    {
        $preview = $this->discounts->previewVoucher(
            hub: $hub,
            items: $request->validated('items'),
            voucherCode: $request->validated('voucher_code'),
            user: $request->user(),
            guestEmail: $request->validated('guest_email'),
        );

        return response()->json(['data' => $preview]);
    }

    /**
     * Upload a GCash/bank-transfer receipt for a pending_payment booking.
     * Only the customer who made the booking may upload their receipt.
     */
    public function uploadReceipt(
        UploadReceiptRequest $request,
        Hub $hub,
        Court $court,
        Booking $booking,
        ImageUploadService $imageUploadService
    ): JsonResponse {
        abort_if($court->hub_id !== $hub->id, 404);
        abort_if($booking->court_id !== $court->id, 404);
        abort_if($booking->booked_by !== $request->user()->id, 403);

        if ($booking->status !== 'pending_payment') {
            return response()->json([
                'message' => 'Receipt can only be uploaded for bookings awaiting payment.',
            ], 422);
        }

        $result = $imageUploadService->upload($request->file('receipt_image'), 'receipts');

        $booking->update([
            'receipt_image_url' => $result['url'],
            'receipt_uploaded_at' => now(),
            'status' => 'payment_sent',
        ]);

        $booking->load('court.hub.owner');
        $this->notifications->notifyReceiptUploaded($booking);

        return response()->json([
            'message' => 'Receipt uploaded. The hub owner will review your payment.',
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'receipt_image_url' => $booking->receipt_image_url,
                'receipt_uploaded_at' => $booking->receipt_uploaded_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Return a QR code PNG for the given booking code. Public — no auth required.
     * The booking code is itself the secret; no additional auth needed.
     */
    public function qrCode(string $code): Response
    {
        $svg = QrCodeFacade::size(300)
            ->margin(2)
            ->color(15, 23, 40)
            ->backgroundColor(255, 255, 255)
            ->generate($code);

        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function resolveExpiresAt(string $paymentMethod, Carbon $startTime): Carbon
    {
        if ($paymentMethod === 'pay_on_site') {
            return $startTime->copy();
        }

        // digital_bank: 1 hour from now, capped at start_time
        return now()->addHour()->min($startTime);
    }
}
