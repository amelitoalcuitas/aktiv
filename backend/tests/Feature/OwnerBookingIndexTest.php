<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Hub;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlaySession;
use App\Models\User;
use Illuminate\Support\Str;

function makeBookingOwner(): User
{
    return User::factory()->create(['role' => 'owner']);
}

function makeBookingPlayer(): User
{
    return User::factory()->create(['role' => 'user']);
}

function makeBookingHub(User $owner): Hub
{
    $hub = Hub::factory()->create([
        'owner_id' => $owner->id,
        'is_approved' => true,
        'is_active' => true,
    ]);

    $hub->settings()->create([
        'payment_methods' => ['pay_on_site', 'digital_bank'],
        'digital_bank_name' => 'Aktiv Test Bank',
        'digital_bank_account' => '1234567890',
    ]);

    return $hub;
}

function makeBookingCourt(Hub $hub, string $name = 'Court A'): Court
{
    return Court::factory()->create([
        'hub_id' => $hub->id,
        'name' => $name,
    ]);
}

function makeOwnerOpenPlaySession(Court $court, array $bookingOverrides = [], array $sessionOverrides = []): OpenPlaySession
{
    $start = now('Asia/Manila')->addDay()->setHour(18)->setMinute(0)->setSecond(0)->utc();
    $end = $start->copy()->addHours(2);

    $booking = Booking::create(array_merge([
        'court_id' => $court->id,
        'sport' => 'badminton',
        'start_time' => $start,
        'end_time' => $end,
        'session_type' => 'open_play',
        'status' => 'confirmed',
        'booking_source' => 'owner_added',
        'total_price' => 0,
    ], $bookingOverrides));

    return OpenPlaySession::create(array_merge([
        'booking_id' => $booking->id,
        'title' => 'Thursday Open Play',
        'max_players' => 6,
        'price_per_player' => 100,
        'guests_can_join' => true,
        'status' => 'open',
    ], $sessionOverrides));
}

it('returns open play participants in the owner bookings index while keeping regular bookings unchanged', function () {
    $owner = makeBookingOwner();
    $hub = makeBookingHub($owner);
    $court = makeBookingCourt($hub);
    $player = makeBookingPlayer();

    $privateBooking = Booking::factory()->create([
        'court_id' => $court->id,
        'booked_by' => $player->id,
        'start_time' => now('Asia/Manila')->addDay()->setHour(8)->setMinute(0)->setSecond(0)->utc(),
        'end_time' => now('Asia/Manila')->addDay()->setHour(9)->setMinute(0)->setSecond(0)->utc(),
        'session_type' => 'private',
        'status' => 'confirmed',
    ]);

    $openPlaySession = makeOwnerOpenPlaySession($court);
    $openPlayBooking = $openPlaySession->booking;

    $registeredParticipant = OpenPlayParticipant::create([
        'open_play_session_id' => $openPlaySession->id,
        'user_id' => $player->id,
        'payment_method' => 'digital_bank',
        'payment_status' => 'payment_sent',
        'receipt_image_url' => 'https://example.com/receipt.jpg',
        'joined_at' => now(),
    ]);

    $cancelledParticipant = OpenPlayParticipant::create([
        'open_play_session_id' => $openPlaySession->id,
        'guest_name' => 'Guest Player',
        'guest_email' => 'guest@example.com',
        'guest_tracking_token' => (string) Str::uuid(),
        'payment_method' => 'pay_on_site',
        'payment_status' => 'cancelled',
        'cancelled_by' => 'owner',
        'joined_at' => now()->addMinute(),
    ]);

    $emptySession = makeOwnerOpenPlaySession(
        $court,
        [
            'start_time' => now('Asia/Manila')->addDay()->setHour(21)->setMinute(0)->setSecond(0)->utc(),
            'end_time' => now('Asia/Manila')->addDay()->setHour(22)->setMinute(0)->setSecond(0)->utc(),
        ],
        [
            'title' => 'Empty Session',
        ]
    );

    $date = now('Asia/Manila')->addDay()->toDateString();

    $response = $this->actingAs($owner)
        ->getJson("/api/dashboard/hubs/{$hub->id}/bookings?date_from={$date}&date_to={$date}")
        ->assertOk();

    $response->assertJsonCount(3, 'data');

    $bookings = collect($response->json('data'))->keyBy('id');
    $openPlayParticipants = collect($bookings[$openPlayBooking->id]['open_play_participants'])->keyBy('id');

    expect($bookings)->toHaveKeys([
        $privateBooking->id,
        $openPlayBooking->id,
        $emptySession->booking->id,
    ]);

    expect($bookings[$emptySession->booking->id]['open_play_participants'])->toBe([])
        ->and($bookings[$openPlayBooking->id]['open_play_session_id'])->toBe($openPlaySession->id)
        ->and($openPlayParticipants)->toHaveCount(2)
        ->and($openPlayParticipants[$registeredParticipant->id]['user']['id'])->toBe($player->id)
        ->and($openPlayParticipants[$registeredParticipant->id]['receipt_image_url'])->toBe('https://example.com/receipt.jpg')
        ->and($openPlayParticipants[$cancelledParticipant->id]['payment_status'])->toBe('cancelled')
        ->and($bookings[$privateBooking->id]['session_type'])->toBe('private')
        ->and($bookings[$privateBooking->id]['booked_by_user']['id'])->toBe($player->id)
        ->and($bookings[$privateBooking->id]['open_play_participants'])->toBe([]);
});
