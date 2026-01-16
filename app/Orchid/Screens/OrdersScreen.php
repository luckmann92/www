<?php

namespace App\Orchid\Screens;

use App\Models\Order;
use App\Models\Photo;
use Orchid\Screen\Actions\Link;
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
            'orders' => Order::with(['collage', 'delivery.telegramUser'])
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
                TD::make('code', 'Номер заказа')
                    ->render(function (Order $order) {
                        return Link::make($order->code ?? "#{$order->id}")
                            ->route('platform.order.view', $order);
                    }),

                TD::make('status', 'Статус')
                    ->render(function (Order $order) {
                        $statusClasses = [
                            'pending' => 'bg-warning',
                            'paid' => 'bg-info',
                            'ready_blurred' => 'bg-primary',
                            'delivered' => 'bg-success',
                            'failed' => 'bg-danger',
                        ];
                        $statusNames = [
                            'pending' => 'Ожидает',
                            'paid' => 'Оплачен',
                            'ready_blurred' => 'Готов',
                            'delivered' => 'Доставлен',
                            'failed' => 'Ошибка',
                        ];
                        $class = $statusClasses[$order->status] ?? 'bg-secondary';
                        $name = $statusNames[$order->status] ?? $order->status;
                        return "<span class='badge {$class}'>{$name}</span>";
                    }),

                TD::make('created_at', 'Дата заказа')
                    ->render(fn (Order $order) => $order->created_at->format('d.m.Y H:i')),

                TD::make('paid_at', 'Дата оплаты')
                    ->render(function (Order $order) {
                        return $order->paid_at ? $order->paid_at->format('d.m.Y H:i') : '-';
                    }),

                TD::make('collage', 'Коллаж')
                    ->render(fn (Order $order) => $order->collage->title ?? '-'),

                TD::make('delivery_type', 'Тип получения')
                    ->render(function (Order $order) {
                        if (!$order->delivery) {
                            return '<span class="text-muted">—</span>';
                        }

                        if ($order->delivery->channel === 'telegram') {
                            $user = $order->delivery->telegramUser;
                            $name = $user ? $user->full_name : 'Telegram';
                            return '<span class="badge bg-info">📱 ' . e($name) . '</span>';
                        } elseif ($order->delivery->channel === 'email') {
                            $email = $order->delivery->email ?? 'Email';
                            return '<span class="badge bg-secondary">📧 ' . e($email) . '</span>';
                        }

                        return $order->delivery->channel;
                    }),

                TD::make('actions', 'Действия')
                    ->width('100px')
                    ->render(function (Order $order) {
                        return Link::make('Открыть')
                            ->icon('eye')
                            ->route('platform.order.view', $order);
                    }),
            ]),
        ];
    }
}
