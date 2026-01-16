<?php

namespace App\Orchid\Screens;

use App\Models\Delivery;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class DeliveriesScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        return [
            'deliveries' => Delivery::with(['order.session'])
                ->orderBy('created_at', 'desc')
                ->paginate(50),
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Доставки';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Список всех доставок';
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
            Layout::table('deliveries', [
                TD::make('id', 'ID')
                    ->width('50px'),

                TD::make('order_id', 'Заказ')
                    ->render(fn (Delivery $delivery) => "#{$delivery->order_id}"),

                TD::make('delivery_type', 'Тип доставки')
                    ->render(fn (Delivery $delivery) => match($delivery->delivery_type) {
                        'email' => '📧 Email',
                        'telegram' => '📱 Telegram',
                        default => $delivery->delivery_type
                    }),

                TD::make('recipient', 'Получатель')
                    ->render(fn (Delivery $delivery) => $delivery->recipient),

                TD::make('status', 'Статус')
                    ->render(function (Delivery $delivery) {
                        $colors = [
                            'pending' => 'warning',
                            'sent' => 'success',
                            'failed' => 'danger',
                        ];
                        $color = $colors[$delivery->status] ?? 'secondary';
                        $statusNames = [
                            'pending' => 'Ожидает',
                            'sent' => 'Отправлено',
                            'failed' => 'Ошибка',
                        ];
                        $statusName = $statusNames[$delivery->status] ?? $delivery->status;
                        return "<span class='badge bg-{$color}'>{$statusName}</span>";
                    }),

                TD::make('sent_at', 'Дата отправки')
                    ->render(fn (Delivery $delivery) => $delivery->sent_at ? $delivery->sent_at->format('d.m.Y H:i') : '-'),

                TD::make('created_at', 'Создано')
                    ->render(fn (Delivery $delivery) => $delivery->created_at->format('d.m.Y H:i')),
            ]),
        ];
    }
}
