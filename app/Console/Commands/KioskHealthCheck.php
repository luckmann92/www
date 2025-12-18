<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KioskHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kiosk:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the health of all kiosks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // In a real implementation, you would fetch a list of kiosks from the DB
        // and ping each one to check its status.
        // For this demo, we'll simulate the check.

        $kiosks = [
            ['id' => 1, 'name' => 'Киоск 1', 'url' => 'http://kiosk1.local/health'],
            ['id' => 2, 'name' => 'Киоск 2', 'url' => 'http://kiosk2.local/health'],
        ];

        foreach ($kiosks as $kiosk) {
            try {
                $response = Http::timeout(10)->get($kiosk['url']);
                $status = $response->successful() ? 'healthy' : 'unhealthy';
            } catch (\Exception $e) {
                $status = 'unreachable';
                Log::error("Kiosk health check failed", [
                    'kiosk_id' => $kiosk['id'],
                    'error' => $e->getMessage(),
                ]);
            }

            $this->info("Kiosk {$kiosk['name']} status: {$status}");
        }

        $this->info('Kiosk health check completed.');
    }
}
