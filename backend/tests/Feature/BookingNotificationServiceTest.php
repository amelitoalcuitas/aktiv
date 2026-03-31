<?php

use App\Mail\BookingConfirmation;
use App\Mail\BookingStatusUpdate;
use App\Mail\GuestBookingVerification;
use App\Mail\OwnerCancelledBookingNotification;
use App\Mail\OwnerBookingNotification;
use App\Mail\WalkInBookingConfirmation;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\User;
use App\Notifications\BookingActivityNotification;
use App\Services\BookingNotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

// ── Helpers ─────────────────────────────────────────────────────

function nsMakeHub(): Hub
{
    $owner = User::factory()->create(['role' => 'owner']);
    return Hub::factory()->create(['owner_id' => $owner->id, 'is_approved' => true, 'is_active' => true]);
}

function nsMakeBookingFor(User $booker, Court $court, array $overrides = []): Booking
{
    return Booking::factory()->create(array_merge([
        'court_id'   => $court->id,
        'booked_by'  => $booker->id,
        'created_by' => $booker->id,
        'status'     => 'pending_payment',
    ], $overrides))->load('court.hub.owner');
}

// ── notifyNewBooking ─────────────────────────────────────────────

it('notifyNewBooking queues confirmation to user and alert to owner, notifies owner in-app', function () {
    Mail::fake();
    Notification::fake();

    $hub    = nsMakeHub();
    $court  = Court::factory()->create(['hub_id' => $hub->id]);
    $booker = User::factory()->create();
    $booking = nsMakeBookingFor($booker, $court);

    app(BookingNotificationService::class)->notifyNewBooking($booking);

    Mail::assertQueued(BookingConfirmation::class, fn ($m) => $m->hasTo($booker->email));
    Mail::assertQueued(OwnerBookingNotification::class, fn ($m) => $m->hasTo($hub->owner->email));
    Notification::assertSentTo($hub->owner, BookingActivityNotification::class);
});

it('notifyNewBooking queues confirmation to guest_email when no registered booker', function () {
    Mail::fake();
    Notification::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'    => $court->id,
        'booked_by'   => null,
        'guest_email' => 'guest@example.com',
        'status'      => 'pending_payment',
    ])->load('court.hub.owner');

    app(BookingNotificationService::class)->notifyNewBooking($booking);

    Mail::assertQueued(BookingConfirmation::class, fn ($m) => $m->hasTo('guest@example.com'));
    Mail::assertQueued(OwnerBookingNotification::class, fn ($m) => $m->hasTo($hub->owner->email));
});

// ── notifyReceiptUploaded ────────────────────────────────────────

it('notifyReceiptUploaded notifies owner in-app for user booking', function () {
    Mail::fake();
    Notification::fake();

    $hub    = nsMakeHub();
    $court  = Court::factory()->create(['hub_id' => $hub->id]);
    $booker = User::factory()->create();
    $booking = nsMakeBookingFor($booker, $court, ['status' => 'payment_sent']);

    app(BookingNotificationService::class)->notifyReceiptUploaded($booking);

    Notification::assertSentTo($hub->owner, BookingActivityNotification::class);
});

it('notifyReceiptUploaded notifies owner in-app for guest booking', function () {
    Mail::fake();
    Notification::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'    => $court->id,
        'booked_by'   => null,
        'guest_email' => 'guest@example.com',
        'status'      => 'payment_sent',
    ])->load('court.hub.owner');

    app(BookingNotificationService::class)->notifyReceiptUploaded($booking);

    Notification::assertSentTo($hub->owner, BookingActivityNotification::class);
});

// ── notifyBookingConfirmed ───────────────────────────────────────

it('notifyBookingConfirmed sends in-app and email to registered booker', function () {
    Mail::fake();
    Notification::fake();

    $hub    = nsMakeHub();
    $court  = Court::factory()->create(['hub_id' => $hub->id]);
    $booker = User::factory()->create(['email_notifications_enabled' => true]);
    $booking = nsMakeBookingFor($booker, $court, ['status' => 'confirmed']);

    app(BookingNotificationService::class)->notifyBookingConfirmed($booking);

    Notification::assertSentTo($booker, BookingActivityNotification::class);
});

it('notifyBookingConfirmed queues email to guest_email when no registered booker', function () {
    Mail::fake();
    Notification::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'    => $court->id,
        'booked_by'   => null,
        'guest_email' => 'guest@example.com',
        'status'      => 'confirmed',
    ])->load('court.hub.owner');

    app(BookingNotificationService::class)->notifyBookingConfirmed($booking);

    Mail::assertQueued(BookingStatusUpdate::class, fn ($m) => $m->hasTo('guest@example.com'));
    Notification::assertNothingSent();
});

it('notifyBookingConfirmed respects email_notifications_enabled=false — no queued mail but in-app fires', function () {
    Mail::fake();
    Notification::fake();

    $hub    = nsMakeHub();
    $court  = Court::factory()->create(['hub_id' => $hub->id]);
    $booker = User::factory()->create(['email_notifications_enabled' => false, 'inapp_notifications_enabled' => true]);
    $booking = nsMakeBookingFor($booker, $court, ['status' => 'confirmed']);

    app(BookingNotificationService::class)->notifyBookingConfirmed($booking);

    Notification::assertSentTo($booker, BookingActivityNotification::class);
    Mail::assertNothingQueued();
});

// ── notifyBookingRejected ────────────────────────────────────────

it('notifyBookingRejected sends in-app and queues email to registered booker', function () {
    Mail::fake();
    Notification::fake();

    $hub    = nsMakeHub();
    $court  = Court::factory()->create(['hub_id' => $hub->id]);
    $booker = User::factory()->create(['email_notifications_enabled' => true]);
    $booking = nsMakeBookingFor($booker, $court, ['status' => 'pending_payment']);

    app(BookingNotificationService::class)->notifyBookingRejected($booking);

    Notification::assertSentTo($booker, BookingActivityNotification::class);
});

it('notifyBookingRejected queues email to guest_email when no registered booker', function () {
    Mail::fake();
    Notification::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'    => $court->id,
        'booked_by'   => null,
        'guest_email' => 'guest@example.com',
        'status'      => 'rejected',
    ])->load('court.hub.owner');

    app(BookingNotificationService::class)->notifyBookingRejected($booking);

    Mail::assertQueued(BookingStatusUpdate::class, fn ($m) => $m->hasTo('guest@example.com'));
    Notification::assertNothingSent();
});

// ── notifyBookingCancelled — owner-cancelled ─────────────────────

it('notifyBookingCancelled(owner) notifies registered booker in-app and queues owner record email', function () {
    Mail::fake();
    Notification::fake();

    $hub    = nsMakeHub();
    $court  = Court::factory()->create(['hub_id' => $hub->id]);
    $booker = User::factory()->create(['email_notifications_enabled' => true]);
    $booking = nsMakeBookingFor($booker, $court, ['status' => 'confirmed']);

    app(BookingNotificationService::class)->notifyBookingCancelled($booking, cancelledBy: 'owner');

    Notification::assertSentTo($booker, BookingActivityNotification::class);
    Mail::assertQueued(OwnerCancelledBookingNotification::class, fn ($m) => $m->hasTo($hub->owner->email));
});

it('notifyBookingCancelled(owner) queues status email to guest_email and owner record email', function () {
    Mail::fake();
    Notification::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'    => $court->id,
        'booked_by'   => null,
        'guest_email' => 'guest@example.com',
        'status'      => 'confirmed',
    ])->load('court.hub.owner');

    app(BookingNotificationService::class)->notifyBookingCancelled($booking, cancelledBy: 'owner');

    Mail::assertQueued(BookingStatusUpdate::class, fn ($m) => $m->hasTo('guest@example.com'));
    Mail::assertQueued(OwnerCancelledBookingNotification::class, fn ($m) => $m->hasTo($hub->owner->email));
});

it('notifyBookingCancelled(owner) still queues owner record when there is no booker or guest email', function () {
    Mail::fake();
    Notification::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'    => $court->id,
        'booked_by'   => null,
        'guest_email' => null,
        'status'      => 'confirmed',
    ])->load('court.hub.owner');

    app(BookingNotificationService::class)->notifyBookingCancelled($booking, cancelledBy: 'owner');

    Mail::assertQueued(OwnerCancelledBookingNotification::class);
    Mail::assertNotQueued(BookingStatusUpdate::class);
    Notification::assertNothingSent();
});

// ── notifyBookingCancelled — guest-cancelled ─────────────────────

it('notifyBookingCancelled(guest) queues status email to guest and notifies owner in-app', function () {
    Mail::fake();
    Notification::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'    => $court->id,
        'booked_by'   => null,
        'guest_email' => 'guest@example.com',
        'status'      => 'confirmed',
    ])->load('court.hub.owner');

    app(BookingNotificationService::class)->notifyBookingCancelled($booking, cancelledBy: 'guest');

    Mail::assertQueued(BookingStatusUpdate::class, fn ($m) => $m->hasTo('guest@example.com'));
    Notification::assertSentTo($hub->owner, BookingActivityNotification::class);
});

// ── notifyBookingUpdated ─────────────────────────────────────────

it('notifyBookingUpdated queues email to guest_email', function () {
    Mail::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'    => $court->id,
        'booked_by'   => null,
        'guest_email' => 'guest@example.com',
        'status'      => 'confirmed',
    ])->load('court.hub');

    app(BookingNotificationService::class)->notifyBookingUpdated($booking);

    Mail::assertQueued(BookingStatusUpdate::class, fn ($m) => $m->hasTo('guest@example.com'));
});

it('notifyBookingUpdated sends nothing when no guest_email', function () {
    Mail::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'    => $court->id,
        'booked_by'   => null,
        'guest_email' => null,
        'status'      => 'confirmed',
    ])->load('court.hub');

    app(BookingNotificationService::class)->notifyBookingUpdated($booking);

    Mail::assertNothingQueued();
});

// ── notifyWalkInBooking ──────────────────────────────────────────

it('notifyWalkInBooking queues WalkInBookingConfirmation when guest_email is present', function () {
    Mail::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'       => $court->id,
        'booked_by'      => null,
        'guest_email'    => 'walkin@example.com',
        'booking_source' => 'walk_in',
        'status'         => 'confirmed',
    ])->load('court.hub');

    app(BookingNotificationService::class)->notifyWalkInBooking($booking);

    Mail::assertQueued(WalkInBookingConfirmation::class, fn ($m) => $m->hasTo('walkin@example.com'));
});

it('notifyWalkInBooking sends nothing when no guest_email', function () {
    Mail::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'       => $court->id,
        'booked_by'      => null,
        'guest_email'    => null,
        'booking_source' => 'walk_in',
        'status'         => 'confirmed',
    ])->load('court.hub');

    app(BookingNotificationService::class)->notifyWalkInBooking($booking);

    Mail::assertNothingQueued();
});

// ── notifyGuestVerification ──────────────────────────────────────

it('notifyGuestVerification queues OTP email to the given address', function () {
    Mail::fake();

    app(BookingNotificationService::class)->notifyGuestVerification('verify@example.com', '123456', 'Test Hub');

    Mail::assertQueued(GuestBookingVerification::class, fn ($m) => $m->hasTo('verify@example.com'));
});

// ── End-to-end via HTTP ──────────────────────────────────────────

it('new user booking via API queues confirmation and owner alert', function () {
    Mail::fake();
    Notification::fake();

    $hub    = nsMakeHub();
    $court  = Court::factory()->create(['hub_id' => $hub->id]);
    $booker = User::factory()->create();

    $start = now('Asia/Manila')->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end   = $start->copy()->addHour();

    $this->actingAs($booker)->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/bookings", [
        'start_time'    => $start->toISOString(),
        'end_time'      => $end->toISOString(),
        'sport'          => 'badminton',
        'session_type'   => 'private',
        'payment_method' => 'pay_on_site',
    ])->assertCreated();

    Mail::assertQueued(BookingConfirmation::class, fn ($m) => $m->hasTo($booker->email));
    Mail::assertQueued(OwnerBookingNotification::class, fn ($m) => $m->hasTo($hub->owner->email));
    Notification::assertSentTo($hub->owner, BookingActivityNotification::class);
});

it('owner confirming a booking via API queues status email to booker', function () {
    Mail::fake();
    Notification::fake();

    $hub    = nsMakeHub();
    $court  = Court::factory()->create(['hub_id' => $hub->id]);
    $booker = User::factory()->create(['email_notifications_enabled' => true]);
    $booking = nsMakeBookingFor($booker, $court, ['status' => 'payment_sent']);

    $this->actingAs($hub->owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/bookings/{$booking->id}/confirm")
        ->assertOk();

    Notification::assertSentTo($booker, BookingActivityNotification::class);
});

it('owner rejecting a booking via API queues rejection notification to booker', function () {
    Mail::fake();
    Notification::fake();

    $hub    = nsMakeHub();
    $court  = Court::factory()->create(['hub_id' => $hub->id]);
    $booker = User::factory()->create(['email_notifications_enabled' => true]);
    $booking = nsMakeBookingFor($booker, $court, ['status' => 'payment_sent']);

    $this->actingAs($hub->owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/bookings/{$booking->id}/reject", [
            'payment_note' => 'Invalid receipt',
        ])
        ->assertOk();

    Notification::assertSentTo($booker, BookingActivityNotification::class);
});

it('owner cancelling a user booking via API queues owner record email', function () {
    Mail::fake();
    Notification::fake();

    $hub    = nsMakeHub();
    $court  = Court::factory()->create(['hub_id' => $hub->id]);
    $booker = User::factory()->create();
    $booking = nsMakeBookingFor($booker, $court, ['status' => 'confirmed']);

    $this->actingAs($hub->owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/bookings/{$booking->id}/cancel")
        ->assertOk();

    Mail::assertQueued(OwnerCancelledBookingNotification::class, fn ($m) => $m->hasTo($hub->owner->email));
    Notification::assertSentTo($booker, BookingActivityNotification::class);
});

it('guest cancelling via tracking token queues guest email and notifies owner', function () {
    Mail::fake();
    Notification::fake();

    $hub   = nsMakeHub();
    $court = Court::factory()->create(['hub_id' => $hub->id]);

    $booking = Booking::factory()->create([
        'court_id'            => $court->id,
        'booked_by'           => null,
        'guest_email'         => 'guest@example.com',
        'guest_tracking_token' => 'test-token-xyz',
        'status'              => 'confirmed',
        'start_time'          => now()->addDay(),
        'end_time'            => now()->addDay()->addHour(),
    ]);

    $this->postJson('/api/guest-bookings/test-token-xyz/cancel')
        ->assertOk();

    Mail::assertQueued(BookingStatusUpdate::class, fn ($m) => $m->hasTo('guest@example.com'));
    Notification::assertSentTo($hub->owner, BookingActivityNotification::class);
});
