<?php

namespace App\Orchid\Screens;

use App\Models\Order;
use App\Models\Photo;
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
            'orders' => Order::with(['session.photos', 'collage', 'payment'])
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

                TD::make('blurred_image', 'Размытое')
                    ->width('100px')
                    ->render(function (Order $order) {
                        $blurredPhoto = $order->session->photos
                            ->where('type', 'result')
                            ->where('blur_level', 80)
                            ->first();

                        if ($blurredPhoto) {
                            $url = asset('storage/' . $blurredPhoto->path);
                            return '<a href="' . $url . '" target="_blank">' .
                                   '<img src="' . $url . '" style="max-width:80px;max-height:60px;border-radius:4px;cursor:pointer;">' .
                                   '</a>';
                        }
                        return '-';
                    }),

                TD::make('ready_image', 'Готовое')
                    ->width('100px')
                    ->render(function (Order $order) {
                        $readyPhoto = $order->session->photos
                            ->where('type', 'result')
                            ->where('blur_level', 0)
                            ->first();

                        if ($readyPhoto) {
                            $url = asset('storage/' . $readyPhoto->path);
                            return '<a href="' . $url . '" target="_blank">' .
                                   '<img src="' . $url . '" style="max-width:80px;max-height:60px;border-radius:4px;cursor:pointer;">' .
                                   '</a>';
                        }
                        return '-';
                    }),

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

                TD::make('paid_at', 'Дата оплаты')
                    ->render(function (Order $order) {
                        if ($order->paid_at) {
                            return $order->paid_at->format('d.m.Y H:i');
                        }
                        return '-';
                    }),

                TD::make('created_at', 'Создан')
                    ->render(fn (Order $order) => $order->created_at->format('d.m.Y H:i')),
            ]),
        ];
    }
}
