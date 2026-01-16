<?php

namespace App\Orchid\Screens;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Persona;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SupportTicketsScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        return [
            'tickets' => SupportTicket::with(['telegramUser', 'order', 'messages'])
                ->orderByRaw("CASE WHEN status = 'new' THEN 1 WHEN status = 'in_progress' THEN 2 ELSE 3 END")
                ->orderBy('created_at', 'desc')
                ->paginate(20),
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Обращения в поддержку';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Управление обращениями пользователей в техническую поддержку';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): array
    {
        return [];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): array
    {
        return [
            Layout::table('tickets', [
                TD::make('id', 'ID')
                    ->width('50px')
                    ->render(fn (SupportTicket $ticket) =>
                        Link::make((string)$ticket->id)
                            ->route('platform.support-ticket.view', $ticket->id)
                    ),

                TD::make('status', 'Статус')
                    ->width('120px')
                    ->render(function (SupportTicket $ticket) {
                        $colors = [
                            'new' => 'danger',
                            'in_progress' => 'warning',
                            'closed' => 'success',
                        ];
                        $color = $colors[$ticket->status] ?? 'secondary';
                        return "<span class='badge bg-{$color}'>{$ticket->status_name}</span>";
                    }),

                TD::make('telegram_user_id', 'Пользователь')
                    ->render(fn (SupportTicket $ticket) => $ticket->telegramUser->full_name ?? 'Неизвестен'),

                TD::make('description', 'Описание проблемы')
                    ->width('300px')
                    ->render(fn (SupportTicket $ticket) => Str::limit($ticket->description, 100)),

                TD::make('order_id', 'Заказ')
                    ->render(fn (SupportTicket $ticket) => $ticket->order ? "#{$ticket->order->id} ({$ticket->order->code})" : '-'),

                TD::make('created_at', 'Создано')
                    ->render(fn (SupportTicket $ticket) => $ticket->created_at->format('d.m.Y H:i')),

                TD::make('actions', 'Действия')
                    ->width('200px')
                    ->align(TD::ALIGN_RIGHT)
                    ->render(function (SupportTicket $ticket) {
                        $html = '<div class="d-flex gap-1 justify-content-end">';

                        if ($ticket->status === SupportTicket::STATUS_NEW) {
                            $html .= ModalToggle::make('В работу')
                                ->modal('takeTicket')
                                ->modalTitle('Взять обращение в работу')
                                ->method('takeInProgress')
                                ->asyncParameters([
                                    'ticket' => $ticket->id,
                                ])
                                ->icon('bs.pencil')
                                ->class('btn btn-sm btn-warning');
                        }

                        if ($ticket->status !== SupportTicket::STATUS_CLOSED) {
                            $html .= ModalToggle::make('Закрыть')
                                ->modal('closeTicket')
                                ->modalTitle('Закрыть обращение')
                                ->method('close')
                                ->asyncParameters([
                                    'ticket' => $ticket->id,
                                ])
                                ->icon('bs.check')
                                ->class('btn btn-sm btn-success');
                        }

                        $html .= '</div>';
                        return $html;
                    }),
            ]),

            Layout::modal('takeTicket', Layout::rows([
                TextArea::make('ticket.description')
                    ->title('Описание проблемы')
                    ->rows(5)
                    ->disabled(),

                TextArea::make('messages_history')
                    ->title('История переписки')
                    ->rows(8)
                    ->disabled(),
            ]))
                ->title('Взять обращение в работу')
                ->applyButton('Взять в работу')
                ->closeButton('Отмена')
                ->async('asyncGetTicket'),

            Layout::modal('closeTicket', Layout::rows([
                TextArea::make('ticket.description')
                    ->title('Описание проблемы')
                    ->rows(3)
                    ->disabled(),

                TextArea::make('messages_history')
                    ->title('История переписки')
                    ->rows(8)
                    ->disabled(),

                TextArea::make('admin_response')
                    ->title('Ответ пользователю')
                    ->rows(5)
                    ->help('Введите ответ для пользователя. Сообщение будет отправлено в Telegram и сохранено в истории.')
                    ->required(),
            ]))
                ->title('Закрыть обращение')
                ->applyButton('Закрыть')
                ->closeButton('Отмена')
                ->async('asyncGetTicket'),
        ];
    }

    /**
     * Асинхронная загрузка данных тикета
     *
     * @param SupportTicket $ticket
     * @return array
     */
    public function asyncGetTicket(SupportTicket $ticket): array
    {
        // Формируем историю сообщений
        $messagesHistory = '';
        foreach ($ticket->messages as $message) {
            $sender = $message->isFromUser() ? '👤 Пользователь' : '👨‍💼 Администратор';
            $time = $message->created_at->format('d.m.Y H:i');
            $messagesHistory .= "[{$time}] {$sender}:\n{$message->message}\n\n";
        }

        return [
            'ticket' => $ticket,
            'messages_history' => $messagesHistory ?: 'История сообщений пуста',
        ];
    }

    /**
     * Взять обращение в работу
     *
     * @param Request $request
     * @return void
     */
    public function takeInProgress(Request $request): void
    {
        $ticket = SupportTicket::findOrFail($request->input('ticket'));
        $ticket->markAsInProgress();

        Toast::info('Обращение взято в работу');
    }

    /**
     * Закрыть обращение
     *
     * @param Request $request
     * @return void
     */
    public function close(Request $request): void
    {
        $request->validate([
            'admin_response' => 'required|string|min:1',
        ]);

        $ticket = SupportTicket::findOrFail($request->input('ticket'));
        $adminResponse = $request->input('admin_response');

        // Сохраняем ответ администратора в историю
        $ticket->addAdminMessage($adminResponse);

        $ticket->close($adminResponse);

        // Отправляем ответ пользователю в Telegram
        if ($ticket->telegramUser) {
            $this->sendResponseToTelegram($ticket->telegramUser->telegram_id, $adminResponse, $ticket->id);
        }

        Toast::info('Обращение закрыто, ответ отправлен пользователю');
    }

    /**
     * Отправить ответ пользователю в Telegram
     *
     * @param int $telegramId
     * @param string $message
     * @return void
     */
    private function sendResponseToTelegram(int $telegramId, string $message, int $ticketId): void
    {
        $token = \App\Models\Setting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN'));
        if (empty($token)) {
            return;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id' => $telegramId,
            'text' => "📨 Ответ от службы поддержки (обращение #{$ticketId}):\n\n{$message}",
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
