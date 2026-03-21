<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\GuestBookingPenalty;
use App\Models\User;
use Illuminate\Console\Command;

class CancelExpiredBookings extends Command
{
    protected $signature = 'bookings:cancel-expired';

    protected $description = 'Cancel pending_payment bookings whose expires_at has passed';

    public function handle(): int
    {
        $expired = Booking::where('status', 'pending_payment')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired bookings found.');
            return self::SUCCESS;
        }

        $ids = $expired->pluck('id');
        Booking::whereIn('id', $ids)->update(['status' => 'cancelled', 'cancelled_by' => 'system']);

        $this->info("Cancelled {$expired->count()} expired booking(s).");

        // Apply strikes to registered users
        $userIds = $expired->whereNotNull('booked_by')->pluck('booked_by')->unique();
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (!$user) continue;
            $this->applyUserStrike($user);
        }

        // Apply strikes to guests by email
        $guestEmails = $expired->whereNotNull('guest_email')->pluck('guest_email')->unique();
        foreach ($guestEmails as $email) {
            $this->applyGuestStrike($email);
        }

        return self::SUCCESS;
    }

    private function applyUserStrike(User $user): void
    {
        // Reset strikes if window has passed (14 days)
        if ($user->strikes_reset_at === null || $user->strikes_reset_at->diffInDays(now()) >= 14) {
            $user->expired_booking_strikes = 0;
            $user->strikes_reset_at = now();
        }

        $user->expired_booking_strikes++;

        if ($user->expired_booking_strikes >= 3) {
            $user->booking_banned_until = now()->addDays(3);
            $user->expired_booking_strikes = 0;
            $user->strikes_reset_at = null;
        }

        $user->save();
    }

    private function applyGuestStrike(string $email): void
    {
        $penalty = GuestBookingPenalty::firstOrCreate(['email' => $email], [
            'strikes' => 0,
        ]);

        // Reset strikes if window has passed (14 days)
        if ($penalty->strikes_reset_at === null || $penalty->strikes_reset_at->diffInDays(now()) >= 14) {
            $penalty->strikes = 0;
            $penalty->strikes_reset_at = now();
        }

        $penalty->strikes++;

        if ($penalty->strikes >= 3) {
            $penalty->banned_until = now()->addDays(3);
            $penalty->strikes = 0;
            $penalty->strikes_reset_at = null;
        }

        $penalty->save();
    }
}
