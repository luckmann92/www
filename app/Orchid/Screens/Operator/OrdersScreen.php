<?php

namespace App\Orchid\Screens\Operator;

use App\Models\Order;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Select;
use Orchid\Support\Facades\Layout as LayoutComponent;
use Orchid\Screen\TD;

class OrdersScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        $status = request('status');

        $query = Order::with(['session', 'collage', 'payment', 'delivery'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return [
            'orders' => $query->paginate(20),
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
        return 'Operator Orders';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Список всех заказов с фильтрацией по статусам';
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
                Select::make('status')
                    ->title('Фильтр по статусу')
                    ->options([
                        '' => 'Все',
                        'created' => 'Создан',
                        'paid' => 'Оплачен',
                        'ready_blurred' => 'Готов (размыт)',
                        'unlocked' => 'Разблокирован',
                        'delivered' => 'Доставлен',
                    ])
                    ->value(request('status'))
                    ->empty('Все статусы'),

                Button::make('Применить')
                    ->icon('filter')
                    ->method('filter'),
            ]),

            LayoutComponent::table('orders', [
                TD::make('id', 'ID')
                    ->sort()
                    ->filter(TD::FILTER_TEXT),

                TD::make('session_id', 'Сессия')
                    ->render(fn (Order $order) => $order->session_id),

                TD::make('collage', 'Коллаж')
                    ->render(fn (Order $order) => $order->collage?->title ?? '-'),

                TD::make('price', 'Цена')
                    ->render(fn (Order $order) => $order->price . ' ₽'),

                TD::make('status', 'Статус')
                    ->render(function (Order $order) {
                        $badges = [
                            'created' => '<span class="badge bg-secondary">Создан</span>',
                            'paid' => '<span class="badge bg-success">Оплачен</span>',
                            'ready_blurred' => '<span class="badge bg-info">Готов (размыт)</span>',
                            'unlocked' => '<span class="badge bg-primary">Разблокирован</span>',
                            'delivered' => '<span class="badge bg-dark">Доставлен</span>',
                        ];
                        return $badges[$order->status] ?? $order->status;
                    }),

                TD::make('payment', 'Оплата')
                    ->render(fn (Order $order) => $order->payment?->status ?? '-'),

                TD::make('delivery', 'Доставка')
                    ->render(fn (Order $order) => $order->delivery?->channel ?? '-'),

                TD::make('created_at', 'Создан')
                    ->render(fn (Order $order) => $order->created_at->format('d.m.Y H:i')),

                TD::make('actions', 'Действия')
                    ->render(function (Order $order) {
                        return Button::make('Просмотр')
                            ->icon('eye')
                            ->method('view', ['id' => $order->id]);
                    }),
            ]),
        ];
    }

    /**
     * Filter orders by status.
     */
    public function filter()
    {
        return redirect()->route('platform.operator.orders', [
            'status' => request('status'),
        ]);
    }
}
