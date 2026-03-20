<?php

namespace Tests\Feature;

use App\Mail\GuestBookingVerification;
use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtSport;
use App\Models\Hub;
use App\Models\HubSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GuestBookingTest extends TestCase
{
    use RefreshDatabase;

    public function makeHub(bool $allowGuests = false): Hub
    {
        $hub = Hub::factory()->create([
            'is_approved' => true,
            'is_active'   => true,
        ]);
        HubSettings::factory()->create([
            'hub_id'                  => $hub->id,
            'require_account_to_book' => ! $allowGuests,
        ]);
        return $hub;
    }

    public function makeCourt(Hub $hub): Court
    {
        $court = Court::factory()->create([
            'hub_id'         => $hub->id,
            'is_active'      => true,
            'price_per_hour' => 200,
        ]);
        CourtSport::factory()->create(['court_id' => $court->id, 'sport' => 'badminton']);
        return $court;
    }

    public function makeHubAndCourt(): array
    {
        $hub = $this->makeHub(allowGuests: true);
        $court = $this->makeCourt($hub);
        return [$hub, $court];
    }


    public function test_returns_403_when_hub_requires_account_for_otp_request(): void
    {
        $hub = $this->makeHub(allowGuests: false);
        $court = $this->makeCourt($hub);

        $response = $this->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/guest-verify", [
            'email' => 'guest@example.com',
        ]);

        $response->assertForbidden();
    }


    public function test_sends_otp_and_returns_200_for_hub_with_guests_allowed(): void
    {
        Mail::fake();
        [$hub, $court] = $this->makeHubAndCourt();

        $response = $this->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/guest-verify", [
            'email' => 'guest@example.com',
        ]);

        $response->assertOk()->assertJsonFragment(['message' => 'Verification code sent. Check your email.']);
        Mail::assertSent(GuestBookingVerification::class);
        $this->assertNotNull(Cache::get("guest_otp:{$hub->id}:guest@example.com"));
    }


    public function test_rejects_otp_request_when_guest_has_active_booking(): void
    {
        Mail::fake();
        [$hub, $court] = $this->makeHubAndCourt();

        Booking::factory()->create([
            'court_id'    => $court->id,
            'guest_email' => 'guest@example.com',
            'status'      => 'pending_payment',
        ]);

        $response = $this->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/guest-verify", [
            'email' => 'guest@example.com',
        ]);

        $response->assertUnprocessable();
        Mail::assertNothingSent();
    }


    public function test_creates_guest_booking_with_valid_otp(): void
    {
        [$hub, $court] = $this->makeHubAndCourt();

        Cache::put("guest_otp:{$hub->id}:guest@example.com", '123456', now()->addMinutes(10));

        $start = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->setMicrosecond(0);
        $end = $start->copy()->addHour();

        $response = $this->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/guest-bookings", [
            'email'        => 'guest@example.com',
            'otp'          => '123456',
            'guest_name'   => 'Juan dela Cruz',
            'sport'        => 'badminton',
            'start_time'   => $start->toISOString(),
            'end_time'     => $end->toISOString(),
            'session_type' => 'private',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending_payment');
        $this->assertDatabaseHas('bookings', ['guest_email' => 'guest@example.com', 'court_id' => $court->id]);
        $this->assertNull(Cache::get("guest_otp:{$hub->id}:guest@example.com"));
    }


    public function test_rejects_booking_with_invalid_otp(): void
    {
        [$hub, $court] = $this->makeHubAndCourt();

        Cache::put("guest_otp:{$hub->id}:guest@example.com", '123456', now()->addMinutes(10));

        $start = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->setMicrosecond(0);
        $end = $start->copy()->addHour();

        $response = $this->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/guest-bookings", [
            'email'        => 'guest@example.com',
            'otp'          => '999999',
            'guest_name'   => 'Juan dela Cruz',
            'sport'        => 'badminton',
            'start_time'   => $start->toISOString(),
            'end_time'     => $end->toISOString(),
            'session_type' => 'private',
        ]);

        $response->assertUnprocessable()->assertJsonFragment(['message' => 'Invalid or expired verification code.']);
    }


    public function test_rejects_booking_exceeding_2_hours(): void
    {
        [$hub, $court] = $this->makeHubAndCourt();

        Cache::put("guest_otp:{$hub->id}:guest@example.com", '123456', now()->addMinutes(10));

        $start = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->setMicrosecond(0);
        $end = $start->copy()->addHours(3);

        $response = $this->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/guest-bookings", [
            'email'        => 'guest@example.com',
            'otp'          => '123456',
            'guest_name'   => 'Juan dela Cruz',
            'sport'        => 'badminton',
            'start_time'   => $start->toISOString(),
            'end_time'     => $end->toISOString(),
            'session_type' => 'private',
        ]);

        $response->assertUnprocessable()->assertJsonFragment(['message' => 'Guest bookings are limited to a maximum of 2 hours.']);
    }


    public function test_rejects_conflicting_guest_booking(): void
    {
        [$hub, $court] = $this->makeHubAndCourt();

        $start = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->setMicrosecond(0);
        $end = $start->copy()->addHour();

        // Existing booking at same slot
        Booking::factory()->create([
            'court_id'   => $court->id,
            'start_time' => $start,
            'end_time'   => $end,
            'status'     => 'confirmed',
        ]);

        Cache::put("guest_otp:{$hub->id}:guest@example.com", '123456', now()->addMinutes(10));

        $response = $this->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/guest-bookings", [
            'email'        => 'guest@example.com',
            'otp'          => '123456',
            'guest_name'   => 'Juan dela Cruz',
            'sport'        => 'badminton',
            'start_time'   => $start->toISOString(),
            'end_time'     => $end->toISOString(),
            'session_type' => 'private',
        ]);

        $response->assertConflict();
    }


    public function test_rejects_duplicate_active_guest_booking(): void
    {
        [$hub, $court] = $this->makeHubAndCourt();

        // Guest already has an active booking
        Booking::factory()->create([
            'court_id'    => $court->id,
            'guest_email' => 'guest@example.com',
            'status'      => 'pending_payment',
        ]);

        Cache::put("guest_otp:{$hub->id}:guest@example.com", '123456', now()->addMinutes(10));

        $start = now()->addDay()->setHour(14)->setMinute(0)->setSecond(0)->setMicrosecond(0);
        $end = $start->copy()->addHour();

        $response = $this->postJson("/api/hubs/{$hub->id}/courts/{$court->id}/guest-bookings", [
            'email'        => 'guest@example.com',
            'otp'          => '123456',
            'guest_name'   => 'Juan dela Cruz',
            'sport'        => 'badminton',
            'start_time'   => $start->toISOString(),
            'end_time'     => $end->toISOString(),
            'session_type' => 'private',
        ]);

        $response->assertUnprocessable();
    }


    public function test_allows_guest_receipt_upload_with_matching_email(): void
    {
        [$hub, $court] = $this->makeHubAndCourt();

        $booking = Booking::factory()->create([
            'court_id'    => $court->id,
            'guest_email' => 'guest@example.com',
            'status'      => 'pending_payment',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('receipt.jpg');

        // Mock the image upload service
        $this->mock(\App\Services\ImageUploadService::class, function ($mock) {
            $mock->shouldReceive('upload')->andReturn(['url' => 'https://example.com/receipt.jpg', 'path' => 'receipts/receipt.jpg']);
        });

        $response = $this->postJson(
            "/api/hubs/{$hub->id}/courts/{$court->id}/guest-bookings/{$booking->id}/receipt",
            ['receipt_image' => $file, 'email' => 'guest@example.com']
        );

        $response->assertOk()->assertJsonPath('data.status', 'payment_sent');
    }


    public function test_rejects_guest_receipt_upload_with_wrong_email(): void
    {
        [$hub, $court] = $this->makeHubAndCourt();

        $booking = Booking::factory()->create([
            'court_id'    => $court->id,
            'guest_email' => 'guest@example.com',
            'status'      => 'pending_payment',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('receipt.jpg');

        $response = $this->postJson(
            "/api/hubs/{$hub->id}/courts/{$court->id}/guest-bookings/{$booking->id}/receipt",
            ['receipt_image' => $file, 'email' => 'wrong@example.com']
        );

        $response->assertForbidden();
    }
}
