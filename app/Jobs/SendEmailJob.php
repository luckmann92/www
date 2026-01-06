<?php

namespace App\Jobs;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Mail\PhotoReadyMail;

class SendEmailJob implements ShouldQueue
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
     * This method sends the photo from the delivery record to the user via email.
     * It updates the delivery status upon success or failure.
     *
     * @return void
     */
    public function handle()
    {
        $delivery = Delivery::findOrFail($this->deliveryId);

        if ($delivery->channel !== 'email') {
            Log::error('SendEmailJob called for non-email delivery', ['delivery_id' => $this->deliveryId]);
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

        $email = $delivery->meta['to'];

        // Create and send the mail using our PhotoReadyMail mailable
        $mail = new PhotoReadyMail($filePath, 'result.jpg');
        Mail::to($email)->send($mail);

        if (Mail::failures()) {
            $delivery->status = 'failed';
            $delivery->meta['error'] = 'Mail delivery failed';
        } else {
            $delivery->status = 'delivered';
            $delivery->meta['status'] = 'sent';
        }

        $delivery->save();
    }
}
