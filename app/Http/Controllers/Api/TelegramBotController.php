<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Photo;
use App\Models\TelegramUser;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $from = $message['from'] ?? null;

        if (!$chatId || !$from) {
            return response()->json(['status' => 'no chat id or user info']);
        }

        // Сохраняем или обновляем пользователя Telegram
        $telegramUser = TelegramUser::createOrUpdate($from);

        // Проверяем, ожидаем ли мы описание проблемы от этого пользователя
        $awaitingSupportDescription = Cache::get("telegram_support_awaiting_{$chatId}");

        // Обработка кнопки "Техническая поддержка"
        if ($text === '🆘 Техническая поддержка') {
            Cache::put("telegram_support_awaiting_{$chatId}", true, now()->addMinutes(30));
            $this->sendMessage($chatId, "Пожалуйста, опишите вашу проблему. Я передам ваше обращение в службу поддержки.", false);
            return response()->json(['status' => 'ok']);
        }

        // Если ожидаем описание проблемы
        if ($awaitingSupportDescription) {
            // Создаем обращение в поддержку
            $ticket = SupportTicket::create([
                'telegram_user_id' => $telegramUser->id,
                'description' => $text,
                'status' => SupportTicket::STATUS_NEW,
            ]);

            // Сохраняем первое сообщение в истории
            $ticket->addUserMessage($text);

            Cache::forget("telegram_support_awaiting_{$chatId}");
            $this->sendMessage($chatId, "Ваше обращение #" . $ticket->id . " принято! Служба поддержки свяжется с вами в ближайшее время.", true);
            return response()->json(['status' => 'ok']);
        }

        // Проверяем, есть ли у пользователя активные тикеты и это не команда
        if (!Str::startsWith($text, '/') && !preg_match('/^\d{3}-\d{3}$/', $text)) {
            $activeTicket = SupportTicket::where('telegram_user_id', $telegramUser->id)
                ->whereIn('status', [SupportTicket::STATUS_NEW, SupportTicket::STATUS_IN_PROGRESS])
                ->orderBy('created_at', 'desc')
                ->first();

            if ($activeTicket) {
                // Добавляем сообщение к активному тикету
                $activeTicket->addUserMessage($text);
                $this->sendMessage($chatId, "Ваше сообщение добавлено к обращению #{$activeTicket->id}. Ожидайте ответа от службы поддержки.", true);
                return response()->json(['status' => 'ok']);
            }
        }

        // Обработка команд и кодов
        $responseText = "Привет! Отправьте код в формате XXX-XXX, чтобы получить ваше фото.";

        if (Str::startsWith($text, '/start')) {
            // Extract code from the command
            $parts = explode(' ', $text);
            if (isset($parts[1])) {
                $code = $parts[1];

                // Validate code format (XXX-XXX)
                if (preg_match('/^\d{3}-\d{3}$/', $code)) {
                    $responseText = $this->processCode($code, $chatId, $telegramUser);
                } else {
                    $responseText = "Неверный формат кода. Пожалуйста, введите код в формате XXX-XXX (например, 123-456).";
                }
            } else {
                // Send welcome message when /start is used without parameters
                $responseText = "Привет! Я бот для отправки фото. Отправьте код, указанный на дисплее.";
            }
        } elseif (preg_match('/^\d{3}-\d{3}$/', $text)) {
            // Direct code input (XXX-XXX)
            $responseText = $this->processCode($text, $chatId, $telegramUser);
        }

        // Send response back to Telegram with keyboard
        $this->sendMessage($chatId, $responseText, true);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Process the provided code and send photo if found
     *
     * @param string $code
     * @param int $chatId
     * @return string
     */
    private function processCode(string $code, int $chatId, TelegramUser $telegramUser): string
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
            ->where('blur_level', 0)
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
    private function sendMessage(int $chatId, string $text, bool $withKeyboard = true): void
    {
        $token = config('telegram.bot_token') ?: env('TELEGRAM_BOT_TOKEN');
        if (empty($token)) {
            \Illuminate\Support\Facades\Log::error('Telegram bot token is not configured');
            return;
        }
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        // Добавляем клавиатуру с кнопкой поддержки
        if ($withKeyboard) {
            $data['reply_markup'] = json_encode([
                'keyboard' => [
                    [
                        ['text' => '🆘 Техническая поддержка']
                    ]
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ]);
        }

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
        $token = config('telegram.bot_token') ?: env('TELEGRAM_BOT_TOKEN');
        if (empty($token)) {
            \Illuminate\Support\Facades\Log::error('Telegram bot token is not configured');
            return;
        }
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
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            \Illuminate\Support\Facades\Log::error('Failed to send message to Telegram API', [
                'url' => $url,
                'data' => $data,
                'error' => error_get_last()
            ]);
        } else {
            $response = json_decode($result, true);
            if (!$response['ok']) {
                \Illuminate\Support\Facades\Log::error('Telegram API returned error', [
                    'url' => $url,
                    'data' => $data,
                    'response' => $response
                ]);
            }
        }
    }
}
