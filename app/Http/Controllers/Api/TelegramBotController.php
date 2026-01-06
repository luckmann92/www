<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TelegramBotController extends Controller
{
    /**
     * Handle incoming webhook from Telegram
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhook(Request $request)
    {
        // Verify the request is from Telegram (optional, depending on your security requirements)
        // You can add bot token verification here if needed

        $update = $request->all();

         // Log the received message
        \Illuminate\Support\Facades\Log::info('Telegram Bot Message Received', $update);

        // Extract message information
        $message = $update['message'] ?? null;
        if (!$message) {
            return response()->json(['status' => 'no message']);
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';
        $userId = $message['from']['id'] ?? null;
        $userName = $message['from']['first_name'] ?? 'Unknown';

        if (!$chatId) {
            return response()->json(['status' => 'no chat id']);
        }


        // Check if the message is a command with code (e.g., /start ABC-DEF)
        $responseText = "Привет! Отправьте код в формате XXX-XXX, чтобы получить ваше фото.";

        if (Str::startsWith($text, '/start')) {
            // Extract code from the command
            $parts = explode(' ', $text);
            if (isset($parts[1])) {
                $code = $parts[1];

                // Validate code format (XXX-XXX)
                if (preg_match('/^\d{3}-\d{3}$/', $code)) {
                    $responseText = $this->processCode($code, $chatId);
                } else {
                    $responseText = "Неверный формат кода. Пожалуйста, введите код в формате XXX-XXX (например, 123-456).";
                }
            } else {
                // Send welcome message when /start is used without parameters
                $responseText = "Привет! Я бот для отправки фото. Отправь номер, указанный на дисплее.";
            }
        } elseif (preg_match('/^\d{3}-\d{3}$/', $text)) {
            // Direct code input (XXX-XXX)
            $responseText = $this->processCode($text, $chatId);
        }

        // Send response back to Telegram
        $this->sendMessage($chatId, $responseText);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Process the provided code and send photo if found
     *
     * @param string $code
     * @param int $chatId
     * @return string
     */
    private function processCode(string $code, int $chatId): string
    {
        // Find order by code
        $order = Order::where('code', $code)->first();

        if (!$order) {
            return "Заказ с кодом {$code} не найден. Пожалуйста, проверьте код и попробуйте снова.";
        }

        // Check if order is paid or ready
        if ($order->status !== 'paid' && $order->status !== 'ready_blurred') {
            return "Заказ с кодом {$code} еще не готов. Пожалуйста, подождите завершения обработки.";
        }

        // Find the non-blurred result image
        $resultPhoto = $order->session->photos()
            ->where('type', 'result')
            ->whereNull('blur_level')
            ->first();

        if (!$resultPhoto) {
            return "Фото для заказа {$code} еще не готово. Пожалуйста, подождите завершения обработки.";
        }

        // Send photo to user
        $this->sendPhoto($chatId, $resultPhoto->path);

        return "Вот ваше фото по заказу {$code}!";
    }

    /**
     * Send a text message to Telegram user
     *
     * @param int $chatId
     * @param string $text
     * @return void
     */
    private function sendMessage(int $chatId, string $text): void
    {
        $token = config('telegram.bot_token');
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        // Send the message
        $this->sendToTelegram($url, $data);
    }

    /**
     * Send a photo to Telegram user
     *
     * @param int $chatId
     * @param string $photoPath
     * @return void
     */
    private function sendPhoto(int $chatId, string $photoPath): void
    {
        $token = config('telegram.bot_token');
        $url = "https://api.telegram.org/bot{$token}/sendPhoto";

        // Get the full URL for the photo
        $photoUrl = Storage::url($photoPath);

        // If it's a relative path, make it absolute
        if (Str::startsWith($photoUrl, '/')) {
            $photoUrl = request()->getSchemeAndHttpHost() . $photoUrl;
        }

        $data = [
            'chat_id' => $chatId,
            'photo' => $photoUrl,
        ];

        // Send the photo
        $this->sendToTelegram($url, $data);
    }

    /**
     * Send request to Telegram API
     *
     * @param string $url
     * @param array $data
     * @return void
     */
    private function sendToTelegram(string $url, array $data): void
    {
        // Using file_get_contents for simplicity, but you might want to use Guzzle or cURL
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
            ],
        ];

        $context = stream_context_create($options);
        file_get_contents($url, false, $context);
    }
}
