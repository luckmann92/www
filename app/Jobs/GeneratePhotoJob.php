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
use Orchid\Attachment\Models\Attachment;

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
     *   3. Gets additional image URLs from the collage.
     *   4. Calls the PhotoComposeService to generate the AI collage via GenAPI.
     *   5. Stores the resulting image and a blurred version.
     *   6. Updates the order status to `ready_blurred`.
     *
     * @return void
     */
    public function handle()
    {
        // 1. Load order with related session and collage
        $order = Order::with(['session', 'collage'])->findOrFail($this->orderId);
        $session = $order->session;
        $collage = $order->collage;

        // 2. Find the original photo for this session
        $originalPhoto = Photo::where('session_id', $session->id)
            ->where('type', 'original')
            ->firstOrFail();

      //  try {
            // 3. Get additional image URLs from collage images_for_generation
            $additionalImageUrls = [];
            if (!empty($collage->images_for_generation)) {
                $previewPaths = is_array($collage->images_for_generation) ? $collage->images_for_generation : [$collage->images_for_generation];

                foreach ($previewPaths as $previewId) {
                    // Check if previewId is a valid attachment ID
                    if (is_numeric($previewId)) {
                        $attachment = Attachment::find($previewId);
                        if ($attachment) {
                            $additionalImageUrls[] = $attachment->url();
                        }
                    } elseif (filter_var($previewId, FILTER_VALIDATE_URL)) {
                        // If it's already a URL, use it directly
                        $additionalImageUrls[] = $previewId;
                    } else {
                        // If it's a relative path, construct the full URL
                        $additionalImageUrls[] = asset('storage/' . $previewId);
                    }
                }
            }

            // 4. Generate AI collage using the service
            $service = app(PhotoComposeInterface::class);
            $result = $service->generate(
                $originalPhoto->path,
                $collage->prompt,
                $additionalImageUrls
            );

            // $result should contain ['image_path' => string, 'blurred_path' => string]

            // 5. Store result records
            Photo::create([
                'session_id' => $session->id,
                'type' => 'result',
                'path' => $result['image_path'],
                'blur_level' => 0,
                'status' => 'ready',
            ]);

            Photo::create([
                'session_id' => $session->id,
                'type' => 'result',
                'path' => $result['blurred_path'],
                'blur_level' => 80,
                'status' => 'ready_blurred',
            ]);

            // 6. Update order status
            $order->status = 'ready_blurred';
            $order->save();
        //} catch (\Exception $e) {
        /*    // В случае ошибки генерации обновляем статус заказа
            $order->status = 'failed';
            $order->save();

            // Логируем ошибку для отладки
            \Illuminate\Support\Facades\Log::error("Error generating photo for order {$this->orderId}: " . $e->getMessage(), [
                'exception' => $e,
                'order_id' => $this->orderId
            ]);

            // Бросаем исключение, чтобы система очередей знала об ошибке
            throw $e;
        }*/

        // Optionally, broadcast an event for WebSocket updates
        // event(new \App\Events\OrderUpdated($order));
    }
}
