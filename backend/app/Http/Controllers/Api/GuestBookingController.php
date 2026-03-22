<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SendsBookingNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\SendGuestVerificationRequest;
use App\Http\Requests\Booking\StoreGuestBookingRequest;
use App\Mail\AdminBookingNotification;
use App\Mail\BookingConfirmation;
use App\Mail\GuestBookingVerification;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Services\ImageUploadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class GuestBookingController extends Controller
{
    use SendsBookingNotification;
    /**
     * Send a 6-digit OTP to the guest's email for booking verification.
     */
    public function sendVerificationCode(SendGuestVerificationRequest $request, Hub $hub, Court $court): JsonResponse
    {
        abort_if($court->hub_id !== $hub->id, 404);

        if ($hub->settings?->require_account_to_book ?? true) {
            return response()->json([
                'message' => 'This hub requires an account to book.',
            ], 403);
        }

        // Check if the guest already has an active booking at this hub
        $courtIds = $hub->courts()->pluck('id');
        $hasActiveBooking = Booking::whereIn('court_id', $courtIds)
            ->where('guest_email', $request->email)
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($hasActiveBooking) {
            return response()->json([
                'message' => 'You already have an active booking at this hub. Guests are limited to 1 active booking per hub.',
            ], 422);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("guest_otp:{$hub->id}:{$request->email}", $code, now()->addMinutes(10));

        Mail::to($request->email)->send(new GuestBookingVerification($code, $hub->name));

        return response()->json([
            'message' => 'Verification code sent. Check your email.',
        ]);
    }

    /**
     * Create a guest booking after OTP verification.
     */
    public function store(StoreGuestBookingRequest $request, Hub $hub, Court $court): JsonResponse
    {
        abort_if($court->hub_id !== $hub->id, 404);

        if ($hub->settings?->require_account_to_book ?? true) {
            return response()->json([
                'message' => 'This hub requires an account to book.',
            ], 403);
        }

        $cacheKey = "guest_otp:{$hub->id}:{$request->email}";
        $storedOtp = Cache::get($cacheKey);

        if ($storedOtp === null || $storedOtp !== $request->otp) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        $startTime = Carbon::parse($request->start_time);
        $endTime = Carbon::parse($request->end_time);

        // Enforce 2-hour maximum for guest bookings
        if ($startTime->diffInMinutes($endTime) > 120) {
            return response()->json([
                'message' => 'Guest bookings are limited to a maximum of 2 hours.',
            ], 422);
        }

        // Enforce 1 active booking per email per hub
        $courtIds = $hub->courts()->pluck('id');
        $hasActiveBooking = Booking::whereIn('court_id', $courtIds)
            ->where('guest_email', $request->email)
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($hasActiveBooking) {
            return response()->json([
                'message' => 'You already have an active booking at this hub. Guests are limited to 1 active booking per hub.',
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

        $hours = $startTime->diffInMinutes($endTime) / 60;
        $pricePerHour = (float) $court->price_per_hour;
        $totalPrice = $pricePerHour > 0 ? round($pricePerHour * $hours, 2) : null;

        $booking = Booking::create([
            'court_id'       => $court->id,
            'booked_by'      => null,
            'created_by'     => null,
            'sport'          => $court->sports->first()?->sport,
            'start_time'     => $startTime,
            'end_time'       => $endTime,
            'session_type'   => $request->session_type,
            'status'         => 'pending_payment',
            'booking_source' => 'self_booked',
            'guest_name'     => $request->guest_name,
            'guest_email'    => $request->email,
            'guest_phone'    => $request->guest_phone,
            'total_price'    => $totalPrice,
            'payment_method' => $request->payment_method,
            'expires_at'     => $this->resolveExpiresAt($request->payment_method, $startTime),
        ]);

        Cache::forget($cacheKey);

        Mail::to($booking->guest_email)->send(new BookingConfirmation($booking, $hub, $court->name));

        // Notify hub owner (in-app + email)
        $owner = $hub->owner()->first();
        if ($owner) {
            $booking->load('court.hub');
            $this->notifyBookingActivity($owner, $booking, 'booking_created');
            Mail::to($owner->email)->send(new AdminBookingNotification($booking, $hub, $court->name));
        }

        return response()->json([
            'message' => 'Booking created successfully.',
            'data' => [
                'id'             => $booking->id,
                'booking_code'   => $booking->booking_code,
                'court_id'       => $booking->court_id,
                'sport'          => $booking->sport,
                'start_time'     => $booking->start_time->toIso8601String(),
                'end_time'       => $booking->end_time->toIso8601String(),
                'session_type'   => $booking->session_type,
                'status'         => $booking->status,
                'booking_source' => $booking->booking_source,
                'total_price'    => $booking->total_price,
                'expires_at'     => $booking->expires_at->toIso8601String(),
                'created_at'     => $booking->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Upload a receipt for a guest booking. Verified by matching guest email.
     */
    public function uploadReceipt(
        Request $request,
        Hub $hub,
        Court $court,
        Booking $booking,
        ImageUploadService $imageUploadService
    ): JsonResponse {
        abort_if($court->hub_id !== $hub->id, 404);
        abort_if($booking->court_id !== $court->id, 404);

        $request->validate([
            'receipt_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'email'         => ['required', 'email'],
        ]);

        if ($booking->guest_email !== $request->email) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($booking->status !== 'pending_payment') {
            return response()->json([
                'message' => 'Receipt can only be uploaded for bookings awaiting payment.',
            ], 422);
        }

        $result = $imageUploadService->upload($request->file('receipt_image'), 'receipts');

        $booking->update([
            'receipt_image_url'    => $result['url'],
            'receipt_uploaded_at'  => now(),
            'status'               => 'payment_sent',
        ]);

        // Notify hub owner about the uploaded receipt
        $owner = $hub->owner()->first();
        if ($owner) {
            $booking->load('court.hub');
            $this->notifyBookingActivity($owner, $booking, 'receipt_uploaded');
        }

        return response()->json([
            'message' => 'Receipt uploaded. The hub owner will review your payment.',
            'data' => [
                'id'                   => $booking->id,
                'status'               => $booking->status,
                'receipt_image_url'    => $booking->receipt_image_url,
                'receipt_uploaded_at'  => $booking->receipt_uploaded_at->toIso8601String(),
            ],
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
