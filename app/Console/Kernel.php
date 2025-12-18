<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Clean up old files and sessions every hour
        $schedule->command('cleanup:old-files')->hourly();

        // Check for stuck jobs and restart them
        $schedule->command('queue:restart-if-stuck')->everyFiveMinutes();

        // Health check for kiosks
        $schedule->command('kiosk:health-check')->everyTenMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
