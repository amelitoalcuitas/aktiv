<?php

use App\Mail\BookingStatusUpdate;
use App\Mail\OwnerCancelledBookingNotification;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Mail::fake();
    Notification::fake();
});

function makeOwnerAndHub(): array
{
    $owner = User::factory()->create(['role' => 'admin']);
    $hub   = Hub::factory()->create(['owner_id' => $owner->id, 'is_approved' => true, 'is_active' => true]);
    $court = Court::factory()->create(['hub_id' => $hub->id]);
    return [$owner, $hub, $court];
}

it('cancels booking and emails owner when they cancel a registered user booking', function () {
    [$owner, $hub, $court] = makeOwnerAndHub();
    $customer = User::factory()->create(['email_notifications_enabled' => true]);

    $booking = Booking::factory()->create([
        'court_id'   => $court->id,
        'booked_by'  => $customer->id,
        'created_by' => $customer->id,
        'status'     => 'confirmed',
    ]);

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/bookings/{$booking->id}/cancel")
        ->assertOk();

    expect($booking->fresh()->status)->toBe('cancelled')
        ->and($booking->fresh()->cancelled_by)->toBe('owner');

    // Owner receives their own confirmation email
    Mail::assertSent(OwnerCancelledBookingNotification::class, function ($mail) use ($owner, $booking) {
        return $mail->hasTo($owner->email)
            && $mail->booking->id === $booking->id;
    });
});

it('cancels booking and emails owner when they cancel a guest booking', function () {
    [$owner, $hub, $court] = makeOwnerAndHub();

    $booking = Booking::factory()->create([
        'court_id'    => $court->id,
        'booked_by'   => null,
        'guest_name'  => 'Walk-in Guest',
        'guest_email' => 'walkin@example.com',
        'status'      => 'pending_payment',
    ]);

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/bookings/{$booking->id}/cancel")
        ->assertOk();

    // Guest gets the customer-facing cancellation email
    Mail::assertSent(BookingStatusUpdate::class, function ($mail) use ($booking) {
        return $mail->hasTo('walkin@example.com');
    });

    // Owner also gets their own confirmation email
    Mail::assertSent(OwnerCancelledBookingNotification::class, function ($mail) use ($owner, $booking) {
        return $mail->hasTo($owner->email)
            && $mail->booking->id === $booking->id;
    });
});

it('cancels booking and emails owner even when there is no customer to notify', function () {
    [$owner, $hub, $court] = makeOwnerAndHub();

    $booking = Booking::factory()->create([
        'court_id'    => $court->id,
        'booked_by'   => null,
        'guest_email' => null,
        'status'      => 'confirmed',
    ]);

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/bookings/{$booking->id}/cancel")
        ->assertOk();

    Mail::assertSent(OwnerCancelledBookingNotification::class, function ($mail) use ($owner) {
        return $mail->hasTo($owner->email);
    });
});

it('returns 422 when trying to cancel an already cancelled booking', function () {
    [$owner, $hub, $court] = makeOwnerAndHub();

    $booking = Booking::factory()->create([
        'court_id'  => $court->id,
        'booked_by' => null,
        'status'    => 'cancelled',
    ]);

    $this->actingAs($owner)
        ->postJson("/api/dashboard/hubs/{$hub->id}/bookings/{$booking->id}/cancel")
        ->assertStatus(422);

    Mail::assertNotSent(OwnerCancelledBookingNotification::class);
});

it('returns 403 when a non-owner tries to cancel', function () {
    [$owner, $hub, $court] = makeOwnerAndHub();
    $other = User::factory()->create(['role' => 'admin']);

    $booking = Booking::factory()->create([
        'court_id'  => $court->id,
        'booked_by' => null,
        'status'    => 'confirmed',
    ]);

    $this->actingAs($other)
        ->postJson("/api/dashboard/hubs/{$hub->id}/bookings/{$booking->id}/cancel")
        ->assertStatus(403);

    Mail::assertNotSent(OwnerCancelledBookingNotification::class);
});
