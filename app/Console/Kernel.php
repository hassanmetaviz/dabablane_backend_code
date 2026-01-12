<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Run the command every day at 00:00
        $schedule->command('blane:check-expiration')->dailyAt('00:00');

        // Run subscription expiration check hourly to catch expirations in near real-time
        // This ensures subscriptions are marked as expired within 1 hour of their end_date
        $schedule->command('subscription:check-expiration --expiration-only')
            ->hourly()
            ->withoutOverlapping();

        // Run subscription expiration check for warnings (7 days before) daily at 10:00 AM
        $schedule->command('subscription:check-expiration --days-before=7')
            ->dailyAt('10:00')
            ->withoutOverlapping();

        // Run subscription expiration check for urgent warnings (3 days before) daily at 11:00 AM
        $schedule->command('subscription:check-expiration --days-before=3')
            ->dailyAt('11:00')
            ->withoutOverlapping();
    }
}
