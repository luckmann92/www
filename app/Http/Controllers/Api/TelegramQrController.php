<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TelegramQrController extends Controller
{
    /**
     * Generate a QR code for Telegram delivery containing a unique link with order UUID
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function generateQr(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        // Create a unique deep-link URL for Telegram with the order code
        $telegramBotUsername = env('TELEGRAM_BOT_USERNAME', 'your_bot_username');
        $deepLinkUrl = "https://t.me/{$telegramBotUsername}?start={$order->code}";

        // Generate QR code
        $renderer = new ImageRenderer(
            new RendererStyle(200), // Size 200x200
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($deepLinkUrl);

        // Return SVG as response
        return response($qrCodeSvg, 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
}
