<?php

namespace App\Jobs;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendTelegramJob implements ShouldQueue
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
     * This method sends the photo from the delivery record to the user via Telegram.
     * It updates the delivery status upon success or failure.
     *
     * @return void
     */
    public function handle()
    {
        $delivery = Delivery::findOrFail($this->deliveryId);

        if ($delivery->channel !== 'telegram') {
            Log::error('SendTelegramJob called for non-telegram delivery', ['delivery_id' => $this->deliveryId]);
            return;
        }

        // Retrieve the file path from delivery meta
        $filePath = $delivery->meta['file_url'] ?? null;

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

        // Get Telegram token from config
        $token = config('telegram.bot_token');
        $url = "https://api.telegram.org/bot{$token}/sendPhoto";

        // This is a placeholder for sending the file via HTTP request
        // In a real project, you would use a Telegram Bot SDK
        $response = Http::attach(
            'photo',
            file_get_contents($absolutePath),
            'result.jpg'
        )->post($url, [
            'chat_id' => $delivery->meta['chat_id'] ?? 'PLACEHOLDER_CHAT_ID',
            'caption' => 'Ваше фото готово!',
        ]);

        if ($response->successful()) {
            $delivery->status = 'delivered';
            $delivery->meta['telegram_response'] = $response->json();
            $delivery->save();
        } else {
            $delivery->status = 'failed';
            $delivery->meta['error'] = $response->body();
            $delivery->save();
        }
    }
}
