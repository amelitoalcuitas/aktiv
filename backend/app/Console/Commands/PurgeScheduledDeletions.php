<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PurgeScheduledDeletions extends Command
{
    protected $signature = 'users:purge-scheduled-deletions';

    protected $description = 'Permanently delete accounts whose 30-day deletion hold has expired.';

    public function handle(): int
    {
        $users = User::query()
            ->whereNotNull('deletion_scheduled_at')
            ->where('deletion_scheduled_at', '<=', now())
            ->get();

        foreach ($users as $user) {
            $user->tokens()->delete();
            $user->forceDelete();
        }

        $this->info("Purged {$users->count()} account(s).");

        return self::SUCCESS;
    }
}
