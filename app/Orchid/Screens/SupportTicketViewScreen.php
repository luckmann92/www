<?php

namespace App\Orchid\Screens;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SupportTicketViewScreen extends Screen
{
    /**
     * @var SupportTicket
     */
    public $ticket;

    /**
     * Query data.
     *
     * @param SupportTicket $ticket
     * @return array
     */
    public function query(SupportTicket $ticket): array
    {
        $ticket->load(['telegramUser', 'order', 'messages']);

        return [
            'ticket' => $ticket,
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Обращение #' . $this->ticket->id;
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Просмотр обращения и истории переписки';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): array
    {
        $actions = [
            Link::make('Назад к списку')
                ->icon('arrow-left')
                ->route('platform.support-tickets'),
        ];

        if ($this->ticket->status === SupportTicket::STATUS_NEW) {
            $actions[] = Button::make('Взять в работу')
                ->icon('pencil')
                ->method('takeInProgress')
                ->type(Color::WARNING)
                ->confirm('Взять это обращение в работу?');
        }

        if ($this->ticket->status !== SupportTicket::STATUS_CLOSED) {
            $actions[] = Button::make('Отправить ответ')
                ->icon('envelope')
                ->method('sendReply')
                ->type(Color::SUCCESS);
        }

        return $actions;
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): array
    {
        $statusColors = [
            'new' => 'danger',
            'in_progress' => 'warning',
            'closed' => 'success',
        ];

        // Формируем информацию о тикете
        $ticketInfo = "Пользователь: {$this->ticket->telegramUser->full_name}\n";
        $ticketInfo .= "Статус: {$this->ticket->status_name}\n";
        $ticketInfo .= "Создано: {$this->ticket->created_at->format('d.m.Y H:i')}\n";
        if ($this->ticket->order) {
            $ticketInfo .= "Заказ: #{$this->ticket->order->id} ({$this->ticket->order->code})\n";
        }
        $ticketInfo .= "\nОписание проблемы:\n{$this->ticket->description}";

        // Формируем историю сообщений
        $messagesHistory = '';
        foreach ($this->ticket->messages as $message) {
            $sender = $message->isFromUser() ? '👤 Пользователь' : '👨‍💼 Администратор';
            $time = $message->created_at->format('d.m.Y H:i:s');
            $messagesHistory .= "═══════════════════════════════════════\n";
            $messagesHistory .= "[{$time}] {$sender}\n";
            $messagesHistory .= "─────────────────────────────────────\n";
            $messagesHistory .= "{$message->message}\n\n";
        }

        $layouts = [
            Layout::rows([
                TextArea::make('ticket_info')
                    ->title('Информация об обращении')
                    ->value($ticketInfo)
                    ->rows(8)
                    ->disabled(),
            ]),

            Layout::rows([
                TextArea::make('messages_history')
                    ->title('История переписки (' . $this->ticket->messages->count() . ' сообщений)')
                    ->value($messagesHistory ?: 'История сообщений пуста')
                    ->rows(20)
                    ->disabled(),
            ]),
        ];

        if ($this->ticket->status !== SupportTicket::STATUS_CLOSED) {
            $layouts[] = Layout::rows([
                TextArea::make('reply_message')
                    ->title('Ответить пользователю')
                    ->rows(5)
                    ->placeholder('Введите ваш ответ...')
                    ->help('Сообщение будет отправлено пользователю в Telegram и сохранено в истории переписки')
                    ->required(),
            ]);
        }

        return $layouts;
    }

    /**
     * Взять обращение в работу
     */
    public function takeInProgress(): void
    {
        $this->ticket->markAsInProgress();
        Toast::info('Обращение взято в работу');
    }

    /**
     * Отправить ответ
     */
    public function sendReply(Request $request): void
    {
        $request->validate([
            'reply_message' => 'required|string|min:1',
        ]);

        $message = $request->input('reply_message');

        // Сохраняем сообщение в историю
        $this->ticket->addAdminMessage($message);

        // Отправляем сообщение в Telegram
        $this->sendMessageToTelegram($this->ticket->telegramUser->telegram_id, $message);

        Toast::info('Ответ отправлен пользователю');
    }

    /**
     * Отправить сообщение пользователю в Telegram
     */
    private function sendMessageToTelegram(int $telegramId, string $message): void
    {
        $token = \App\Models\Setting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN'));
        if (empty($token)) {
            return;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id' => $telegramId,
            'text' => "📨 Ответ от службы поддержки (обращение #{$this->ticket->id}):\n\n{$message}",
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
            ],
        ];

        $context = stream_context_create($options);
        @file_get_contents($url, false, $context);
    }
}
