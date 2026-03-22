<?php

use App\Mail\BookingStatusUpdate;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\User;
use App\Notifications\BookingActivityNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
    Notification::fake();
});

function makeGuestBookingForTracking(): array
{
    $owner = User::factory()->create(['role' => 'admin']);
    $hub   = Hub::factory()->create(['owner_id' => $owner->id, 'is_approved' => true, 'is_active' => true]);
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'             => $court->id,
        'booked_by'            => null,
        'guest_name'           => 'Test Guest',
        'guest_email'          => 'guest@example.com',
        'guest_tracking_token' => Str::uuid(),
        'status'               => 'pending_payment',
        'payment_method'       => 'pay_on_site',
    ]);

    return [$owner, $hub, $court, $booking];
}

it('sets booking to cancelled when guest cancels via tracking token', function () {
    [, , , $booking] = makeGuestBookingForTracking();

    $this->postJson("/api/guest-bookings/{$booking->guest_tracking_token}/cancel")
        ->assertOk();

    expect($booking->fresh()->status)->toBe('cancelled')
        ->and($booking->fresh()->cancelled_by)->toBe('user');
});

it('emails the guest a cancellation confirmation when they cancel', function () {
    [, , , $booking] = makeGuestBookingForTracking();

    $this->postJson("/api/guest-bookings/{$booking->guest_tracking_token}/cancel")
        ->assertOk();

    Mail::assertSent(BookingStatusUpdate::class, function ($mail) use ($booking) {
        return $mail->hasTo($booking->guest_email);
    });
});

it('notifies hub owner with booking_cancelled_by_guest activity when a guest cancels', function () {
    [$owner, , , $booking] = makeGuestBookingForTracking();

    $this->postJson("/api/guest-bookings/{$booking->guest_tracking_token}/cancel")
        ->assertOk();

    Notification::assertSentTo($owner, BookingActivityNotification::class, function ($notification) {
        return (new ReflectionClass($notification))
            ->getProperty('activityType')
            ->getValue($notification) === 'booking_cancelled_by_guest';
    });
});

it('returns 422 when booking is already cancelled', function () {
    [, , , $booking] = makeGuestBookingForTracking();
    $booking->update(['status' => 'cancelled']);

    $this->postJson("/api/guest-bookings/{$booking->guest_tracking_token}/cancel")
        ->assertStatus(422);
});

it('returns 422 when booking has already ended', function () {
    [, , , $booking] = makeGuestBookingForTracking();
    $booking->update([
        'start_time' => now()->subHours(3),
        'end_time'   => now()->subHours(2),
    ]);

    $this->postJson("/api/guest-bookings/{$booking->guest_tracking_token}/cancel")
        ->assertStatus(422);
});
