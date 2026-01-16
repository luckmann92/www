<?php

namespace App\Orchid\Screens;

use App\Models\Order;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class OrdersScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        return [
            'orders' => Order::with(['session', 'collage', 'payment'])
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
        return 'Заказы';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Список всех заказов';
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
            Layout::table('orders', [
                TD::make('id', 'ID')
                    ->width('50px'),

                TD::make('code', 'Код')
                    ->render(fn (Order $order) => $order->code ?? '-'),

                TD::make('session_id', 'Сессия')
                    ->render(fn (Order $order) => "#{$order->session_id}"),

                TD::make('collage', 'Коллаж')
                    ->render(fn (Order $order) => $order->collage->title ?? '-'),

                TD::make('price', 'Цена')
                    ->render(fn (Order $order) => $order->price . ' ₽'),

                TD::make('status', 'Статус')
                    ->render(function (Order $order) {
                        $statusNames = [
                            'pending' => 'Ожидает',
                            'paid' => 'Оплачен',
                            'ready_blurred' => 'Готов (размыт)',
                            'delivered' => 'Доставлен',
                            'failed' => 'Ошибка',
                        ];
                        return $statusNames[$order->status] ?? $order->status;
                    }),

                TD::make('created_at', 'Создан')
                    ->render(fn (Order $order) => $order->created_at->format('d.m.Y H:i')),
            ]),
        ];
    }
}
