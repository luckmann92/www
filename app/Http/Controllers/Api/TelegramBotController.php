<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\TelegramUser;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        Log::info('Telegram Bot Message Received', $update);

        // Extract message information
        $message = $update['message'] ?? null;
        if (!$message) {
            return response()->json(['status' => 'no message']);
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';
        $from = $message['from'] ?? null;
        $replyToMessage = $message['reply_to_message'] ?? null;

        if (!$chatId || !$from) {
            return response()->json(['status' => 'no chat id or user info']);
        }

        // Сохраняем или обновляем пользователя Telegram
        $telegramUser = TelegramUser::createOrUpdate($from);
        $username = $from['username'] ?? null;

        // Проверяем, является ли пользователь оператором поддержки
        $isSupport = $this->isSupportOperator($username);

        // ===== ОБРАБОТКА ЗАПРОСОВ ОПЕРАТОРА =====
        if ($isSupport) {
            return $this->handleOperatorRequest($chatId, $text, $telegramUser, $replyToMessage);
        }

        // ===== ОБРАБОТКА ЗАПРОСОВ ОБЫЧНОГО ПОЛЬЗОВАТЕЛЯ =====

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

            // Отправляем уведомление операторам
            $this->notifySupportOperators($ticket, $telegramUser);

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

                // Уведомляем операторов о новом сообщении
                $this->notifySupportOperatorsAboutNewMessage($activeTicket, $text, $telegramUser);

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
        $token = Setting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN'));
        if (empty($token)) {
            Log::error('Telegram bot token is not configured');
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
        $token = Setting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN'));
        if (empty($token)) {
            Log::error('Telegram bot token is not configured');
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
            Log::error('Failed to send message to Telegram API', [
                'url' => $url,
                'data' => $data,
                'error' => error_get_last()
            ]);
        } else {
            $response = json_decode($result, true);
            if (!$response['ok']) {
                Log::error('Telegram API returned error', [
                    'url' => $url,
                    'data' => $data,
                    'response' => $response
                ]);
            }
        }
    }

    /**
     * Check if user is a support operator
     *
     * @param string|null $username
     * @return bool
     */
    private function isSupportOperator(?string $username): bool
    {
        if (!$username) {
            return false;
        }

        $supportUsers = Setting::get('telegram_support_users', '');
        if (empty($supportUsers)) {
            return false;
        }

        // Parse usernames from settings (one per line, may start with @)
        $operators = array_filter(array_map(function ($line) {
            $line = trim($line);
            return ltrim($line, '@'); // Remove @ prefix if present
        }, explode("\n", $supportUsers)));

        return in_array(ltrim($username, '@'), $operators, true);
    }

    /**
     * Get list of support operator chat IDs
     *
     * @return array
     */
    private function getSupportOperatorChatIds(): array
    {
        $supportUsers = Setting::get('telegram_support_users', '');
        if (empty($supportUsers)) {
            return [];
        }

        // Parse usernames from settings
        $operators = array_filter(array_map(function ($line) {
            $line = trim($line);
            return ltrim($line, '@');
        }, explode("\n", $supportUsers)));

        // Find TelegramUser records and get their telegram_id
        $chatIds = [];
        foreach ($operators as $username) {
            $user = TelegramUser::where('username', $username)->first();
            if ($user) {
                $chatIds[] = $user->telegram_id;
            }
        }

        return $chatIds;
    }

    /**
     * Notify support operators about new ticket
     *
     * @param SupportTicket $ticket
     * @param TelegramUser $user
     * @return void
     */
    private function notifySupportOperators(SupportTicket $ticket, TelegramUser $user): void
    {
        $chatIds = $this->getSupportOperatorChatIds();

        if (empty($chatIds)) {
            Log::info('No support operators configured to notify about ticket', ['ticket_id' => $ticket->id]);
            return;
        }

        $message = "🆘 Новое обращение в поддержку!\n\n";
        $message .= "Тикет #{$ticket->id}\n";
        $message .= "От: {$user->full_name}";
        if ($user->username) {
            $message .= " (@{$user->username})";
        }
        $message .= "\n\n";
        $message .= "📝 Описание:\n{$ticket->description}\n\n";
        $message .= "💡 Для ответа: ответьте на это сообщение или используйте /reply_{$ticket->id} ваш текст";

        foreach ($chatIds as $chatId) {
            $this->sendMessageToOperator($chatId, $message);
        }
    }

    /**
     * Notify support operators about new message in ticket
     *
     * @param SupportTicket $ticket
     * @param string $messageText
     * @param TelegramUser $user
     * @return void
     */
    private function notifySupportOperatorsAboutNewMessage(SupportTicket $ticket, string $messageText, TelegramUser $user): void
    {
        $chatIds = $this->getSupportOperatorChatIds();

        if (empty($chatIds)) {
            return;
        }

        $message = "💬 Новое сообщение в тикете!\n\n";
        $message .= "Тикет #{$ticket->id}\n";
        $message .= "От: {$user->full_name}";
        if ($user->username) {
            $message .= " (@{$user->username})";
        }
        $message .= "\n\n";
        $message .= "📝 Сообщение:\n{$messageText}\n\n";
        $message .= "💡 Для ответа: ответьте на это сообщение или используйте /reply_{$ticket->id} ваш текст";

        foreach ($chatIds as $chatId) {
            $this->sendMessageToOperator($chatId, $message);
        }
    }

    /**
     * Send message to operator (without support keyboard)
     *
     * @param int $chatId
     * @param string $text
     * @return void
     */
    private function sendMessageToOperator(int $chatId, string $text): void
    {
        $token = Setting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN'));
        if (empty($token)) {
            Log::error('Telegram bot token is not configured');
            return;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        $this->sendToTelegram($url, $data);
    }

    /**
     * Handle all operator requests
     *
     * @param int $chatId
     * @param string $text
     * @param TelegramUser $operator
     * @param array|null $replyToMessage
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleOperatorRequest(int $chatId, string $text, TelegramUser $operator, ?array $replyToMessage)
    {
        // Проверяем, ожидаем ли мы текст ответа от оператора
        $awaitingReply = Cache::get("operator_reply_awaiting_{$chatId}");
        if ($awaitingReply && !Str::startsWith($text, '/')) {
            // Оператор написал текст ответа
            Cache::forget("operator_reply_awaiting_{$chatId}");
            return $this->handleOperatorReply($awaitingReply, $text, $operator, $chatId);
        }

        // Обработка ответа оператора через reply на сообщение
        if ($replyToMessage) {
            $replyText = $replyToMessage['text'] ?? '';
            if (preg_match('/Тикет #(\d+)/u', $replyText, $matches)) {
                $ticketId = (int) $matches[1];
                return $this->handleOperatorReply($ticketId, $text, $operator, $chatId);
            }
        }

        // Отмена текущей операции
        if ($text === '/cancel' || $text === '❌ Отмена') {
            Cache::forget("operator_reply_awaiting_{$chatId}");
            $this->sendOperatorMessage($chatId, "Действие отменено.");
            return response()->json(['status' => 'ok']);
        }

        // /start - показать приветствие и список тикетов
        if ($text === '/start' || Str::startsWith($text, '/start ')) {
            Cache::forget("operator_reply_awaiting_{$chatId}");
            return $this->showOperatorWelcome($chatId);
        }

        // /tickets - показать список открытых тикетов
        if ($text === '/tickets' || $text === '📋 Открытые тикеты') {
            Cache::forget("operator_reply_awaiting_{$chatId}");
            return $this->showOpenTickets($chatId);
        }

        // /ticket_N - показать детали тикета
        if (preg_match('/^\/ticket_(\d+)$/u', $text, $matches)) {
            Cache::forget("operator_reply_awaiting_{$chatId}");
            $ticketId = (int) $matches[1];
            return $this->showTicketDetails($ticketId, $chatId);
        }

        // /reply_N текст - ответить на тикет с текстом
        if (preg_match('/^\/reply_(\d+)\s+(.+)$/su', $text, $matches)) {
            Cache::forget("operator_reply_awaiting_{$chatId}");
            $ticketId = (int) $matches[1];
            $replyText = $matches[2];
            return $this->handleOperatorReply($ticketId, $replyText, $operator, $chatId);
        }

        // /reply_N без текста - перейти в режим ожидания ответа
        if (preg_match('/^\/reply_(\d+)$/u', $text, $matches)) {
            $ticketId = (int) $matches[1];
            return $this->startReplyMode($ticketId, $chatId);
        }

        // /close_N - закрыть тикет
        if (preg_match('/^\/close_(\d+)$/u', $text, $matches)) {
            Cache::forget("operator_reply_awaiting_{$chatId}");
            $ticketId = (int) $matches[1];
            return $this->closeTicket($ticketId, $chatId);
        }

        // По умолчанию показываем справку
        $this->showOperatorHelp($chatId);
        return response()->json(['status' => 'ok']);
    }

    /**
     * Start reply mode - waiting for operator to type response
     *
     * @param int $ticketId
     * @param int $chatId
     * @return \Illuminate\Http\JsonResponse
     */
    private function startReplyMode(int $ticketId, int $chatId)
    {
        $ticket = SupportTicket::with(['telegramUser', 'messages'])->find($ticketId);

        if (!$ticket) {
            $this->sendOperatorMessage($chatId, "❌ Тикет #{$ticketId} не найден.");
            return response()->json(['status' => 'ok']);
        }

        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            $this->sendOperatorMessage($chatId, "❌ Тикет #{$ticketId} уже закрыт.");
            return response()->json(['status' => 'ok']);
        }

        // Сохраняем ID тикета в кэш для ожидания ответа
        Cache::put("operator_reply_awaiting_{$chatId}", $ticketId, now()->addMinutes(30));

        // Показываем контекст тикета
        $message = "✍️ Ответ на Тикет #{$ticket->id}\n\n";
        $message .= "👤 Пользователь: {$ticket->telegramUser->full_name}\n";
        $message .= "📝 Описание: " . Str::limit($ticket->description, 100) . "\n\n";

        // Последние сообщения
        $lastMessages = $ticket->messages->take(-3);
        if ($lastMessages->count() > 0) {
            $message .= "💬 Последние сообщения:\n";
            foreach ($lastMessages as $msg) {
                $sender = $msg->isFromUser() ? '👤' : '👨‍💼';
                $msgText = Str::limit($msg->message, 50);
                $message .= "{$sender}: {$msgText}\n";
            }
            $message .= "\n";
        }

        $message .= "📝 Введите текст ответа:\n";
        $message .= "(или /cancel для отмены)";

        $this->sendOperatorMessageWithCancel($chatId, $message);
        return response()->json(['status' => 'ok']);
    }

    /**
     * Send message to operator with cancel button
     *
     * @param int $chatId
     * @param string $text
     * @return void
     */
    private function sendOperatorMessageWithCancel(int $chatId, string $text): void
    {
        $token = Setting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN'));
        if (empty($token)) {
            Log::error('Telegram bot token is not configured');
            return;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode([
                'keyboard' => [
                    [
                        ['text' => '❌ Отмена']
                    ],
                    [
                        ['text' => '📋 Открытые тикеты']
                    ]
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ]),
        ];

        $this->sendToTelegram($url, $data);
    }

    /**
     * Show operator welcome message with open tickets
     *
     * @param int $chatId
     * @return \Illuminate\Http\JsonResponse
     */
    private function showOperatorWelcome(int $chatId)
    {
        $message = "👨‍💼 Панель оператора поддержки\n\n";
        $message .= "Доступные команды:\n";
        $message .= "/tickets - Список открытых тикетов\n";
        $message .= "/ticket_N - Просмотр тикета N\n";
        $message .= "/reply_N текст - Ответить на тикет N\n";
        $message .= "/close_N - Закрыть тикет N\n\n";
        $message .= "Также можно ответить на уведомление о тикете (reply).\n";

        $this->sendOperatorMessage($chatId, $message);

        // Сразу показываем открытые тикеты
        return $this->showOpenTickets($chatId);
    }

    /**
     * Show help for operator
     *
     * @param int $chatId
     * @return void
     */
    private function showOperatorHelp(int $chatId): void
    {
        $message = "👨‍💼 Панель оператора поддержки\n\n";
        $message .= "Доступные команды:\n";
        $message .= "/tickets - Список открытых тикетов\n";
        $message .= "/ticket_N - Просмотр тикета N\n";
        $message .= "/reply_N текст - Ответить на тикет N\n";
        $message .= "/close_N - Закрыть тикет N\n\n";
        $message .= "Также можно ответить на уведомление о тикете (reply).";

        $this->sendOperatorMessage($chatId, $message);
    }

    /**
     * Show list of open tickets to operator
     *
     * @param int $chatId
     * @return \Illuminate\Http\JsonResponse
     */
    private function showOpenTickets(int $chatId)
    {
        $tickets = SupportTicket::with('telegramUser')
            ->whereIn('status', [SupportTicket::STATUS_NEW, SupportTicket::STATUS_IN_PROGRESS])
            ->orderByRaw("CASE WHEN status = 'new' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($tickets->isEmpty()) {
            $this->sendOperatorMessage($chatId, "✅ Нет открытых тикетов!");
            return response()->json(['status' => 'ok']);
        }

        $message = "📋 Открытые тикеты ({$tickets->count()}):\n\n";

        foreach ($tickets as $ticket) {
            $statusIcon = $ticket->status === SupportTicket::STATUS_NEW ? '🆕' : '🔄';
            $userName = $ticket->telegramUser->full_name ?? 'Неизвестен';
            $description = Str::limit($ticket->description, 50);

            $message .= "{$statusIcon} Тикет #{$ticket->id}\n";
            $message .= "👤 {$userName}\n";
            $message .= "📝 {$description}\n";
            $message .= "📅 {$ticket->created_at->format('d.m.Y H:i')}\n";
            $message .= "➡️ /ticket_{$ticket->id}\n\n";
        }

        $this->sendOperatorMessage($chatId, $message);
        return response()->json(['status' => 'ok']);
    }

    /**
     * Show ticket details to operator
     *
     * @param int $ticketId
     * @param int $chatId
     * @return \Illuminate\Http\JsonResponse
     */
    private function showTicketDetails(int $ticketId, int $chatId)
    {
        $ticket = SupportTicket::with(['telegramUser', 'messages'])->find($ticketId);

        if (!$ticket) {
            $this->sendOperatorMessage($chatId, "❌ Тикет #{$ticketId} не найден.");
            return response()->json(['status' => 'ok']);
        }

        $statusNames = [
            'new' => '🆕 Новый',
            'in_progress' => '🔄 В работе',
            'closed' => '✅ Закрыт',
        ];

        $message = "📋 Тикет #{$ticket->id}\n\n";
        $message .= "Статус: {$statusNames[$ticket->status]}\n";
        $message .= "👤 Пользователь: {$ticket->telegramUser->full_name}";
        if ($ticket->telegramUser->username) {
            $message .= " (@{$ticket->telegramUser->username})";
        }
        $message .= "\n";
        $message .= "📅 Создан: {$ticket->created_at->format('d.m.Y H:i')}\n\n";
        $message .= "📝 Описание:\n{$ticket->description}\n\n";

        // История сообщений
        if ($ticket->messages->count() > 0) {
            $message .= "💬 История сообщений:\n";
            $message .= "─────────────────\n";
            foreach ($ticket->messages->take(10) as $msg) {
                $sender = $msg->isFromUser() ? '👤' : '👨‍💼';
                $time = $msg->created_at->format('d.m H:i');
                $msgText = Str::limit($msg->message, 100);
                $message .= "{$sender} [{$time}]: {$msgText}\n";
            }
            $message .= "─────────────────\n\n";
        }

        if ($ticket->status !== SupportTicket::STATUS_CLOSED) {
            $message .= "💡 Действия:\n";
            $message .= "/reply_{$ticket->id} текст - Ответить\n";
            $message .= "/close_{$ticket->id} - Закрыть тикет\n";
        }

        $this->sendOperatorMessage($chatId, $message);
        return response()->json(['status' => 'ok']);
    }

    /**
     * Close ticket
     *
     * @param int $ticketId
     * @param int $chatId
     * @return \Illuminate\Http\JsonResponse
     */
    private function closeTicket(int $ticketId, int $chatId)
    {
        $ticket = SupportTicket::with('telegramUser')->find($ticketId);

        if (!$ticket) {
            $this->sendOperatorMessage($chatId, "❌ Тикет #{$ticketId} не найден.");
            return response()->json(['status' => 'ok']);
        }

        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            $this->sendOperatorMessage($chatId, "❌ Тикет #{$ticketId} уже закрыт.");
            return response()->json(['status' => 'ok']);
        }

        $ticket->close();

        // Notify user
        if ($ticket->telegramUser) {
            $userMessage = "✅ Ваше обращение #{$ticket->id} закрыто.\n\nЕсли у вас есть другие вопросы, нажмите кнопку \"🆘 Техническая поддержка\".";
            $this->sendMessage($ticket->telegramUser->telegram_id, $userMessage, true);
        }

        $this->sendOperatorMessage($chatId, "✅ Тикет #{$ticket->id} закрыт.");

        Log::info('Support ticket closed by operator', [
            'ticket_id' => $ticket->id,
            'operator_chat_id' => $chatId,
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Send message to operator with operator keyboard
     *
     * @param int $chatId
     * @param string $text
     * @return void
     */
    private function sendOperatorMessage(int $chatId, string $text): void
    {
        $token = Setting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN'));
        if (empty($token)) {
            Log::error('Telegram bot token is not configured');
            return;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode([
                'keyboard' => [
                    [
                        ['text' => '📋 Открытые тикеты']
                    ]
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ]),
        ];

        $this->sendToTelegram($url, $data);
    }

    /**
     * Handle operator reply to ticket
     *
     * @param int $ticketId
     * @param string $replyText
     * @param TelegramUser $operator
     * @param int $operatorChatId
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleOperatorReply(int $ticketId, string $replyText, TelegramUser $operator, int $operatorChatId)
    {
        $ticket = SupportTicket::with('telegramUser')->find($ticketId);

        if (!$ticket) {
            $this->sendMessageToOperator($operatorChatId, "❌ Тикет #{$ticketId} не найден.");
            return response()->json(['status' => 'ticket not found']);
        }

        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            $this->sendMessageToOperator($operatorChatId, "❌ Тикет #{$ticketId} уже закрыт.");
            return response()->json(['status' => 'ticket closed']);
        }

        // Mark ticket as in progress if it's new
        if ($ticket->status === SupportTicket::STATUS_NEW) {
            $ticket->markAsInProgress();
        }

        // Save admin message to history
        $ticket->addAdminMessage($replyText);

        // Send reply to user
        if ($ticket->telegramUser) {
            $userMessage = "📨 Ответ от службы поддержки (обращение #{$ticket->id}):\n\n{$replyText}";
            $this->sendMessage($ticket->telegramUser->telegram_id, $userMessage, true);
        }

        // Confirm to operator
        $this->sendMessageToOperator($operatorChatId, "✅ Ответ на тикет #{$ticket->id} отправлен пользователю.");

        Log::info('Support operator replied to ticket', [
            'ticket_id' => $ticket->id,
            'operator_id' => $operator->id,
            'operator_username' => $operator->username,
        ]);

        return response()->json(['status' => 'ok']);
    }
}
