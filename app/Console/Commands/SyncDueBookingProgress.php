<?php

namespace App\Console\Commands;

use App\Services\BookingProgressSyncService;
use Illuminate\Console\Command;

class SyncDueBookingProgress extends Command
{
    protected $signature = 'bookings:sync-progress';

    protected $description = 'Start and complete confirmed bookings whose scheduled times are due';

    public function handle(BookingProgressSyncService $progressSync): int
    {
        $updated = $progressSync->sync();

        $this->info("Booking progress synchronized ({$updated} updates). ");

        return self::SUCCESS;
    }
}
