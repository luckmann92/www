<?php

namespace App\Jobs;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The ID of the delivery record.
     *
     * @var int
     */
    public int $deliveryId;

    /**
     * Create a new job instance.
     *
     * @param  int  $deliveryId
     * @return void
     */
    public function __construct(int $deliveryId)
    {
        $this->deliveryId = $deliveryId;
    }

    /**
     * Execute the job.
     *
     * This method sends the photo from the delivery record to a local print agent.
     * It updates the delivery status upon success or failure.
     *
     * @return void
     */
    public function handle()
    {
        $delivery = Delivery::findOrFail($this->deliveryId);

        if ($delivery->channel !== 'print') {
            Log::error('PrintJob called for non-print delivery', ['delivery_id' => $this->deliveryId]);
            return;
        }

        // Retrieve the file path from delivery meta
        $filePath = $delivery->meta['file_path'] ?? null;

        if (!$filePath) {
            $delivery->status = 'failed';
            $delivery->meta['error'] = 'File path not found in delivery meta';
            $delivery->save();
            return;
        }

        // Get the absolute path for the file
        $absolutePath = Storage::disk('local')->path($filePath);

        if (!file_exists($absolutePath)) {
            $delivery->status = 'failed';
            $delivery->meta['error'] = 'File does not exist on disk';
            $delivery->save();
            return;
        }

        // Placeholder for calling a local print agent
        // Example: execute a shell command to print the file
        $command = "lp -o fit-to-page " . escapeshellarg($absolutePath);
        $exitCode = 0;
        $output = [];
        exec($command, $output, $exitCode);

        if ($exitCode === 0) {
            $delivery->status = 'delivered';
            $delivery->meta['print_response'] = $output;
        } else {
            $delivery->status = 'failed';
            $delivery->meta['error'] = 'Print command failed with exit code: ' . $exitCode;
        }

        $delivery->save();
    }
}
