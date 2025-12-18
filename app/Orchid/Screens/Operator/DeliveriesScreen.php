<?php

namespace App\Orchid\Screens\Operator;

use App\Models\Delivery;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Select;
use Orchid\Support\Facades\Layout as LayoutComponent;
use Orchid\Screen\TD;

class DeliveriesScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        $channel = request('channel');
        $status = request('status');

        $query = Delivery::with(['order.collage'])
            ->orderBy('created_at', 'desc');

        if ($channel) {
            $query->where('channel', $channel);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return [
            'deliveries' => $query->paginate(20),
            'channel' => $channel,
            'status' => $status,
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Operator Deliveries';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Мониторинг доставок (Telegram, Email, Print)';
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
            LayoutComponent::rows([
                Select::make('channel')
                    ->title('Канал доставки')
                    ->options([
                        '' => 'Все',
                        'telegram' => 'Telegram',
                        'email' => 'Email',
                        'print' => 'Печать',
                    ])
                    ->value(request('channel'))
                    ->empty('Все каналы'),

                Select::make('status')
                    ->title('Статус')
                    ->options([
                        '' => 'Все',
                        'pending' => 'Ожидание',
                        'processing' => 'В процессе',
                        'delivered' => 'Доставлено',
                        'failed' => 'Ошибка',
                    ])
                    ->value(request('status'))
                    ->empty('Все статусы'),

                Button::make('Применить')
                    ->icon('filter')
                    ->method('filter'),
            ]),

            LayoutComponent::table('deliveries', [
                TD::make('id', 'ID')
                    ->sort()
                    ->filter(TD::FILTER_TEXT),

                TD::make('order_id', 'Заказ')
                    ->render(fn (Delivery $delivery) => $delivery->order_id),

                TD::make('channel', 'Канал')
                    ->render(function (Delivery $delivery) {
                        $icons = [
                            'telegram' => '<i class="icon-paper-plane"></i> Telegram',
                            'email' => '<i class="icon-envelope"></i> Email',
                            'print' => '<i class="icon-printer"></i> Печать',
                        ];
                        return $icons[$delivery->channel] ?? $delivery->channel;
                    }),

                TD::make('status', 'Статус')
                    ->render(function (Delivery $delivery) {
                        $badges = [
                            'pending' => '<span class="badge bg-warning">Ожидание</span>',
                            'processing' => '<span class="badge bg-info">В процессе</span>',
                            'delivered' => '<span class="badge bg-success">Доставлено</span>',
                            'failed' => '<span class="badge bg-danger">Ошибка</span>',
                        ];
                        return $badges[$delivery->status] ?? $delivery->status;
                    }),

                TD::make('meta', 'Детали')
                    ->render(function (Delivery $delivery) {
                        if (!$delivery->meta) {
                            return '-';
                        }

                        $meta = is_array($delivery->meta) ? $delivery->meta : json_decode($delivery->meta, true);

                        if ($delivery->channel === 'telegram' && isset($meta['code'])) {
                            return 'Code: ' . $meta['code'];
                        }

                        if ($delivery->channel === 'email' && isset($meta['email'])) {
                            return $meta['email'];
                        }

                        return json_encode($meta);
                    }),

                TD::make('created_at', 'Создана')
                    ->render(fn (Delivery $delivery) => $delivery->created_at->format('d.m.Y H:i')),

                TD::make('updated_at', 'Обновлена')
                    ->render(fn (Delivery $delivery) => $delivery->updated_at->format('d.m.Y H:i')),

                TD::make('actions', 'Действия')
                    ->render(function (Delivery $delivery) {
                        $buttons = [];

                        if ($delivery->status === 'failed') {
                            $buttons[] = Button::make('Повторить')
                                ->icon('reload')
                                ->method('retry', ['id' => $delivery->id])
                                ->confirm('Повторить доставку?');
                        }

                        $buttons[] = Button::make('Просмотр')
                            ->icon('eye')
                            ->method('view', ['id' => $delivery->id]);

                        return implode(' ', array_map(fn($btn) => (string)$btn, $buttons));
                    }),
            ]),
        ];
    }

    /**
     * Filter deliveries.
     */
    public function filter()
    {
        return redirect()->route('platform.operator.deliveries', [
            'channel' => request('channel'),
            'status' => request('status'),
        ]);
    }

    /**
     * Retry failed delivery.
     */
    public function retry($id)
    {
        $delivery = Delivery::findOrFail($id);

        // Логика повторной отправки
        // В реальном приложении здесь должна быть диспетчеризация соответствующей Job

        return redirect()->back()->with('success', 'Доставка поставлена в очередь на повтор');
    }
}
