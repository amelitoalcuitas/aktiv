<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\User;
use App\Notifications\BookingActivityNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

// ── Helpers ────────────────────────────────────────────────────

function makeBookingWithRelations(User $user): Booking
{
    $hub   = Hub::factory()->create(['is_approved' => true, 'is_active' => true]);
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    return Booking::factory()->create([
        'court_id'   => $court->id,
        'booked_by'  => $user->id,
        'created_by' => $user->id,
        'status'     => 'confirmed',
    ]);
}

// ── via() channel selection ────────────────────────────────────

it('includes database channel when inapp_notifications_enabled is true', function () {
    $user     = User::factory()->create(['inapp_notifications_enabled' => true]);
    $booking  = makeBookingWithRelations($user);
    $booking->load('court.hub');

    $notification = new BookingActivityNotification($booking, 'booking_confirmed');
    $channels     = $notification->via($user);

    expect($channels)->toContain('database');
});

it('excludes database channel when inapp_notifications_enabled is false', function () {
    $user     = User::factory()->create(['inapp_notifications_enabled' => false]);
    $booking  = makeBookingWithRelations($user);
    $booking->load('court.hub');

    $notification = new BookingActivityNotification($booking, 'booking_confirmed');
    $channels     = $notification->via($user);

    expect($channels)->not->toContain('database');
});

it('includes mail channel for booking_confirmed when email_notifications_enabled is true', function () {
    $user     = User::factory()->create(['email_notifications_enabled' => true]);
    $booking  = makeBookingWithRelations($user);
    $booking->load('court.hub');

    $notification = new BookingActivityNotification($booking, 'booking_confirmed');
    $channels     = $notification->via($user);

    expect($channels)->toContain('mail');
});

it('excludes mail channel when email_notifications_enabled is false', function () {
    $user     = User::factory()->create(['email_notifications_enabled' => false]);
    $booking  = makeBookingWithRelations($user);
    $booking->load('court.hub');

    $notification = new BookingActivityNotification($booking, 'booking_confirmed');
    $channels     = $notification->via($user);

    expect($channels)->not->toContain('mail');
});

it('does not include mail channel for booking_created', function () {
    $user     = User::factory()->create(['email_notifications_enabled' => true]);
    $booking  = makeBookingWithRelations($user);
    $booking->load('court.hub');

    $notification = new BookingActivityNotification($booking, 'booking_created');
    $channels     = $notification->via($user);

    expect($channels)->not->toContain('mail');
});

it('includes mail channel for receipt_uploaded', function () {
    $owner = User::factory()->create(['email_notifications_enabled' => true]);
    $hub   = Hub::factory()->create(['owner_id' => $owner->id, 'is_approved' => true, 'is_active' => true]);
    $court = Court::factory()->create(['hub_id' => $hub->id]);
    $user  = User::factory()->create();

    $booking = Booking::factory()->create([
        'court_id'   => $court->id,
        'booked_by'  => $user->id,
        'created_by' => $user->id,
    ]);
    $booking->load('court.hub');

    $notification = new BookingActivityNotification($booking, 'receipt_uploaded');
    $channels     = $notification->via($owner);

    expect($channels)->toContain('mail');
});

it('includes mail channel for booking_rejected and booking_cancelled', function () {
    $user    = User::factory()->create(['email_notifications_enabled' => true]);
    $booking = makeBookingWithRelations($user);
    $booking->load('court.hub');

    foreach (['booking_rejected', 'booking_cancelled'] as $type) {
        $notification = new BookingActivityNotification($booking, $type);
        expect($notification->via($user))->toContain('mail');
    }
});

// ── Actual mail delivery ───────────────────────────────────────

it('sends booking_confirmed email to user', function () {
    Notification::fake();

    $user    = User::factory()->create(['email_notifications_enabled' => true, 'inapp_notifications_enabled' => true]);
    $booking = makeBookingWithRelations($user);
    $booking->load('court.hub');

    $user->notify(new BookingActivityNotification($booking, 'booking_confirmed'));

    Notification::assertSentTo($user, BookingActivityNotification::class, function ($n, $channels) {
        return in_array('mail', $channels) && in_array('database', $channels);
    });
});

it('does not send email when email_notifications_enabled is false but still sends in-app', function () {
    Notification::fake();

    $user    = User::factory()->create(['email_notifications_enabled' => false, 'inapp_notifications_enabled' => true]);
    $booking = makeBookingWithRelations($user);
    $booking->load('court.hub');

    $user->notify(new BookingActivityNotification($booking, 'booking_confirmed'));

    Notification::assertSentTo($user, BookingActivityNotification::class, function ($n, $channels) {
        return ! in_array('mail', $channels) && in_array('database', $channels);
    });
});

it('does not send in-app notification when inapp_notifications_enabled is false but still sends email', function () {
    Notification::fake();

    $user    = User::factory()->create(['email_notifications_enabled' => true, 'inapp_notifications_enabled' => false]);
    $booking = makeBookingWithRelations($user);
    $booking->load('court.hub');

    $user->notify(new BookingActivityNotification($booking, 'booking_confirmed'));

    Notification::assertSentTo($user, BookingActivityNotification::class, function ($n, $channels) {
        return in_array('mail', $channels) && ! in_array('database', $channels);
    });
});
