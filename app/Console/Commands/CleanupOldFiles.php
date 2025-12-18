<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:old-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old photo files and sessions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ttlHours = config('app.photo_ttl_hours', 24);
        $cutoffTime = now()->subHours($ttlHours);

        // Find and delete old photo files
        $files = Storage::disk('local')->allFiles('photos/originals');
        foreach ($files as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);
            if ($lastModified < $cutoffTime->timestamp) {
                Storage::disk('local')->delete($file);
                $this->info("Deleted old file: {$file}");
            }
        }

        // You can also clean up old sessions/orders from DB here if needed
        // Example: Order::where('created_at', '<', $cutoffTime)->delete();

        $this->info('Old file cleanup completed.');
    }
}
