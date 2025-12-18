<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Photo;
use App\Services\PhotoComposeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GeneratePhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The ID of the order for which the AI collage should be generated.
     *
     * @var int
     */
    public int $orderId;

    /**
     * Create a new job instance.
     *
     * @param  int  $orderId
     * @return void
     */
    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     *
     * This method:
     *   1. Retrieves the order and its session.
     *   2. Finds the original uploaded photo for the session.
     *   3. Calls the PhotoComposeService to generate the AI collage via OpenRouter.
     *   4. Stores the resulting image and a blurred version.
     *   5. Updates the order status to `ready_blurred`.
     *
     * @return void
     */
    public function handle()
    {
        // 1. Load order with related session
        $order = Order::with('session')->findOrFail($this->orderId);
        $session = $order->session;

        // 2. Find the original photo for this session
        $originalPhoto = Photo::where('session_id', $session->id)
            ->where('type', 'original')
            ->firstOrFail();

        // 3. Generate AI collage using the service
        $service = app(PhotoComposeInterface::class);
        $result = $service->generate(
            $originalPhoto->path,
            $order->collage->prompt
        );

        // $result should contain ['image_path' => string, 'blurred_path' => string]

        // 4. Store result records
        Photo::create([
            'session_id' => $session->id,
            'type' => 'result',
            'path' => $result['image_path'],
            'blur_level' => null,
            'status' => 'ready',
        ]);

        Photo::create([
            'session_id' => $session->id,
            'type' => 'result',
            'path' => $result['blurred_path'],
            'blur_level' => 80,
            'status' => 'ready_blurred',
        ]);

        // 5. Update order status
        $order->status = 'ready_blurred';
        $order->save();

        // Optionally, broadcast an event for WebSocket updates
        // event(new \App\Events\OrderUpdated($order));
    }
}
