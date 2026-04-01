<?php

use App\Events\NotificationBroadcast;
use App\Mail\OpenPlayOwnerReceiptNotification;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlaySession;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
});

function makeGuestTrackingOwner(): User
{
    return User::factory()->create(['role' => 'owner']);
}

function makeGuestTrackingHub(User $owner): Hub
{
    $hub = Hub::factory()->create(['owner_id' => $owner->id, 'is_approved' => true, 'is_active' => true]);

    $hub->settings()->create([
        'payment_methods'      => ['pay_on_site', 'digital_bank'],
        'digital_bank_name'    => 'Aktiv Test Bank',
        'digital_bank_account' => '1234567890',
    ]);

    return $hub;
}

function makeGuestTrackingCourt(Hub $hub): Court
{
    return Court::factory()->create(['hub_id' => $hub->id]);
}

function makeGuestTrackingSession(Court $court, array $overrides = []): OpenPlaySession
{
    $start = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $end = $start->copy()->addHours(2);

    $booking = Booking::create(array_merge([
        'court_id'       => $court->id,
        'sport'          => 'badminton',
        'start_time'     => $start,
        'end_time'       => $end,
        'session_type'   => 'open_play',
        'status'         => 'confirmed',
        'booking_source' => 'owner_added',
        'total_price'    => 0,
    ], $overrides['booking'] ?? []));

    return OpenPlaySession::create(array_merge([
        'booking_id'       => $booking->id,
        'sport'            => 'badminton',
        'max_players'      => 4,
        'price_per_player' => 150.00,
        'guests_can_join'  => true,
        'status'           => 'open',
    ], $overrides['session'] ?? []));
}

function makeGuestTrackedParticipant(OpenPlaySession $session, array $overrides = []): OpenPlayParticipant
{
    return $session->participants()->create(array_merge([
        'guest_name'           => 'Tracked Guest',
        'guest_email'          => 'guest@example.com',
        'guest_tracking_token' => (string) Str::uuid(),
        'payment_method'       => 'digital_bank',
        'payment_status'       => 'pending_payment',
        'joined_at'            => now(),
        'expires_at'           => now()->addHour(),
    ], $overrides));
}

it('guest can fetch tracked open play join by valid token', function () {
    $owner = makeGuestTrackingOwner();
    $hub = makeGuestTrackingHub($owner);
    $hub->contactNumbers()->create(['type' => 'mobile', 'number' => '+639171234567']);
    $hub->websites()->create(['platform' => 'facebook', 'url' => 'https://facebook.com/aktiv']);
    $court = makeGuestTrackingCourt($hub);
    $session = makeGuestTrackingSession($court);
    $participant = makeGuestTrackedParticipant($session, ['payment_note' => 'Bring your reference number.']);

    $this->getJson("/api/guest-open-play/{$participant->guest_tracking_token}")
        ->assertOk()
        ->assertJsonPath('data.id', $participant->id)
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.payment_method', 'digital_bank')
        ->assertJsonPath('data.guest_name', 'Tracked Guest')
        ->assertJsonPath('data.court.id', $court->id)
        ->assertJsonPath('data.hub.id', $hub->id)
        ->assertJsonPath('data.hub.phones.0', '+639171234567')
        ->assertJsonPath('data.hub.websites.0.platform', 'facebook')
        ->assertJsonPath('data.payment_note', 'Bring your reference number.');
});

it('guest open play tracking returns not found for invalid token', function () {
    $this->getJson('/api/guest-open-play/invalid-token')->assertNotFound();
});

it('guest can upload receipt via tracking token for pending digital bank join', function () {
    Event::fake([NotificationBroadcast::class]);

    $owner = makeGuestTrackingOwner();
    $hub = makeGuestTrackingHub($owner);
    $court = makeGuestTrackingCourt($hub);
    $session = makeGuestTrackingSession($court);
    $participant = makeGuestTrackedParticipant($session);

    $this->mock(ImageUploadService::class, function ($mock) {
        $mock->shouldReceive('upload')
            ->once()
            ->andReturn([
                'url'  => 'https://example.com/receipts/open-play-receipt.jpg',
                'path' => 'receipts/open-play-receipt.jpg',
            ]);
    });

    $file = UploadedFile::fake()->image('receipt.jpg');

    $this->postJson("/api/guest-open-play/{$participant->guest_tracking_token}/receipt", [
        'receipt_image' => $file,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'payment_sent')
        ->assertJsonPath('data.receipt_image_url', 'https://example.com/receipts/open-play-receipt.jpg');

    expect($participant->fresh()->payment_status)->toBe('payment_sent')
        ->and($participant->fresh()->receipt_image_url)->toBe('https://example.com/receipts/open-play-receipt.jpg');

    Mail::assertQueued(OpenPlayOwnerReceiptNotification::class, fn ($mail) => $mail->hasTo($owner->email));
});

it('guest cannot upload receipt for pay on site joins', function () {
    $owner = makeGuestTrackingOwner();
    $hub = makeGuestTrackingHub($owner);
    $court = makeGuestTrackingCourt($hub);
    $session = makeGuestTrackingSession($court);
    $participant = makeGuestTrackedParticipant($session, ['payment_method' => 'pay_on_site']);

    $this->postJson("/api/guest-open-play/{$participant->guest_tracking_token}/receipt", [
        'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Receipt upload is only available for digital bank payment.');
});

it('guest cannot upload receipt once the join is no longer pending payment', function (string $status) {
    $owner = makeGuestTrackingOwner();
    $hub = makeGuestTrackingHub($owner);
    $court = makeGuestTrackingCourt($hub);
    $session = makeGuestTrackingSession($court);
    $participant = makeGuestTrackedParticipant($session, ['payment_status' => $status]);

    $this->postJson("/api/guest-open-play/{$participant->guest_tracking_token}/receipt", [
        'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Receipt can only be uploaded when payment is pending.');
})->with(['payment_sent', 'confirmed', 'cancelled']);

it('guest cannot upload receipt for expired joins', function () {
    $owner = makeGuestTrackingOwner();
    $hub = makeGuestTrackingHub($owner);
    $court = makeGuestTrackingCourt($hub);
    $session = makeGuestTrackingSession($court);
    $participant = makeGuestTrackedParticipant($session, ['expires_at' => now()->subMinute()]);

    $this->postJson("/api/guest-open-play/{$participant->guest_tracking_token}/receipt", [
        'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This open play join has expired and can no longer receive a receipt.');
});

it('guest cannot upload receipt after the session has ended', function () {
    $owner = makeGuestTrackingOwner();
    $hub = makeGuestTrackingHub($owner);
    $court = makeGuestTrackingCourt($hub);
    $session = makeGuestTrackingSession($court, [
        'booking' => [
            'start_time' => now()->subHours(3),
            'end_time'   => now()->subHours(2),
        ],
    ]);
    $participant = makeGuestTrackedParticipant($session);

    $this->postJson("/api/guest-open-play/{$participant->guest_tracking_token}/receipt", [
        'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This open play session has already ended.');
});

it('guest can cancel a tracked join and release the reserved seat', function (string $status) {
    $owner = makeGuestTrackingOwner();
    $hub = makeGuestTrackingHub($owner);
    $court = makeGuestTrackingCourt($hub);
    $session = makeGuestTrackingSession($court, [
        'session' => ['max_players' => 1],
    ]);
    $participant = makeGuestTrackedParticipant($session, ['payment_status' => $status]);

    $session->recalculateStatus();

    expect($session->fresh()->status)->toBe('full');

    $this->postJson("/api/guest-open-play/{$participant->guest_tracking_token}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancelled_by', 'user');

    expect($participant->fresh()->payment_status)->toBe('cancelled')
        ->and($participant->fresh()->cancelled_by)->toBe('user')
        ->and($session->fresh()->status)->toBe('open');
})->with(['pending_payment', 'payment_sent']);

it('guest cannot cancel confirmed or already cancelled joins', function (string $status) {
    $owner = makeGuestTrackingOwner();
    $hub = makeGuestTrackingHub($owner);
    $court = makeGuestTrackingCourt($hub);
    $session = makeGuestTrackingSession($court);
    $participant = makeGuestTrackedParticipant($session, ['payment_status' => $status]);

    $this->postJson("/api/guest-open-play/{$participant->guest_tracking_token}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('message', 'This open play join cannot be cancelled.');
})->with(['confirmed', 'cancelled']);

it('guest cannot cancel a tracked join after the session has ended', function () {
    $owner = makeGuestTrackingOwner();
    $hub = makeGuestTrackingHub($owner);
    $court = makeGuestTrackingCourt($hub);
    $session = makeGuestTrackingSession($court, [
        'booking' => [
            'start_time' => now()->subHours(3),
            'end_time'   => now()->subHours(2),
        ],
    ]);
    $participant = makeGuestTrackedParticipant($session);

    $this->postJson("/api/guest-open-play/{$participant->guest_tracking_token}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('message', 'This open play session has already ended and can no longer be cancelled.');
});

it('guest open play cancellation returns not found for invalid token', function () {
    $this->postJson('/api/guest-open-play/invalid-token/cancel')->assertNotFound();
});
