<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class CancelExpiredBookings extends Command
{
    protected $signature = 'bookings:cancel-expired';

    protected $description = 'Cancel pending_payment bookings whose expires_at has passed';

    public function handle(): int
    {
        $count = Booking::where('status', 'pending_payment')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'cancelled']);

        $this->info("Cancelled {$count} expired booking(s).");

        return self::SUCCESS;
    }
}
