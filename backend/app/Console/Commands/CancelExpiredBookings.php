<?php

namespace App\Console\Commands;

use App\Events\BookingSlotUpdated;
use App\Models\Booking;
use App\Models\GuestBookingPenalty;
use App\Models\OpenPlayParticipant;
use App\Models\User;
use App\Services\OpenPlayNotificationService;
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
            ->with('court')
            ->get();

        if (! $expired->isEmpty()) {
            $ids = $expired->pluck('id');
            Booking::whereIn('id', $ids)->update(['status' => 'cancelled', 'cancelled_by' => 'system']);

            $this->info("Cancelled {$expired->count()} expired booking(s).");

            // Broadcast slot updates so scheduler grids update in real time
            foreach ($expired as $booking) {
                broadcast(new BookingSlotUpdated(
                    hubId: $booking->court->hub_id,
                    courtId: $booking->court_id,
                    status: 'cancelled',
                ));
            }

            // Apply strikes to registered users
            $userIds = $expired->whereNotNull('booked_by')->pluck('booked_by')->unique();
            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if (! $user) continue;
                $this->applyUserStrike($user);
            }

            // Apply strikes to guests by email
            $guestEmails = $expired->whereNotNull('guest_email')->pluck('guest_email')->unique();
            foreach ($guestEmails as $email) {
                $this->applyGuestStrike($email);
            }
        }

        // Cancel expired open play participants (pending_payment or payment_sent past expires_at)
        $expiredParticipants = OpenPlayParticipant::whereIn('payment_status', ['pending_payment', 'payment_sent'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['openPlaySession.booking.court.hub', 'user'])
            ->get();

        if ($expiredParticipants->isNotEmpty()) {
            $participantIds = $expiredParticipants->pluck('id');
            OpenPlayParticipant::whereIn('id', $participantIds)
                ->update(['payment_status' => 'cancelled', 'cancelled_by' => 'system']);

            $this->info("Cancelled {$expiredParticipants->count()} expired open play participant(s).");

            $openPlayNotifications = app(OpenPlayNotificationService::class);

            // Track which were pending_payment before we mutate the models (payment_sent expirations
            // are the owner's fault for not reviewing — no strike applied to those participants).
            $pendingPaymentParticipants = $expiredParticipants->where('payment_status', 'pending_payment');

            foreach ($expiredParticipants as $participant) {
                $participant->payment_status = 'cancelled';
                $session = $participant->openPlaySession;
                $session->recalculateStatus();
                $openPlayNotifications->notifyParticipantCancelled($participant, $session, 'system');

                broadcast(new BookingSlotUpdated(
                    hubId: $session->booking->court->hub_id,
                    courtId: $session->booking->court_id,
                    status: 'open_play',
                ));
            }

            $strikableUserIds = $pendingPaymentParticipants->whereNotNull('user_id')->pluck('user_id')->unique();
            foreach ($strikableUserIds as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $this->applyUserStrike($user);
                }
            }

            $strikableGuestEmails = $pendingPaymentParticipants->whereNotNull('guest_email')->pluck('guest_email')->unique();
            foreach ($strikableGuestEmails as $email) {
                $this->applyGuestStrike($email);
            }
        }

        // Send session-started notifications to confirmed participants
        $startedSessions = \App\Models\OpenPlaySession::whereIn('status', ['open', 'full'])
            ->whereNull('start_notification_sent_at')
            ->whereHas('booking', fn ($q) => $q
                ->where('status', 'confirmed')
                ->where('start_time', '<=', now())
            )
            ->with(['booking.court.hub', 'participants' => fn ($q) => $q->where('payment_status', 'confirmed')->with('user')])
            ->get();

        if ($startedSessions->isNotEmpty()) {
            $openPlayNotifications = app(OpenPlayNotificationService::class);

            foreach ($startedSessions as $session) {
                $openPlayNotifications->notifySessionStarted($session);
                $session->update(['start_notification_sent_at' => now()]);
            }

            $this->info("Sent session-started notifications for {$startedSessions->count()} open play session(s).");
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
