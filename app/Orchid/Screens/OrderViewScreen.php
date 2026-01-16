<?php

namespace App\Orchid\Screens;

use App\Models\Order;
use App\Models\Photo;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\Sight;
use Orchid\Support\Facades\Layout;

class OrderViewScreen extends Screen
{
    /**
     * @var Order
     */
    public $order;

    /**
     * Query data.
     *
     * @param Order $order
     * @return array
     */
    public function query(Order $order): array
    {
        $order->load(['session.photos', 'collage', 'payment', 'delivery.telegramUser']);

        return [
            'order' => $order,
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Заказ #' . ($this->order->code ?? $this->order->id);
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Детальная информация о заказе';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): array
    {
        return [
            Link::make('Назад к списку')
                ->icon('arrow-left')
                ->route('platform.orders'),
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): array
    {
        return [
            Layout::legend('order', [
                Sight::make('id', 'ID'),
                Sight::make('code', 'Код заказа'),
                Sight::make('status', 'Статус')->render(function (Order $order) {
                    $statusNames = [
                        'pending' => '<span class="badge bg-warning">Ожидает</span>',
                        'paid' => '<span class="badge bg-info">Оплачен</span>',
                        'ready_blurred' => '<span class="badge bg-primary">Готов (размыт)</span>',
                        'delivered' => '<span class="badge bg-success">Доставлен</span>',
                        'failed' => '<span class="badge bg-danger">Ошибка</span>',
                    ];
                    return $statusNames[$order->status] ?? $order->status;
                }),
                Sight::make('collage', 'Коллаж')->render(function (Order $order) {
                    return $order->collage->title ?? '-';
                }),
                Sight::make('price', 'Цена')->render(function (Order $order) {
                    return $order->price . ' ₽';
                }),
                Sight::make('created_at', 'Дата создания')->render(function (Order $order) {
                    return $order->created_at->format('d.m.Y H:i');
                }),
                Sight::make('paid_at', 'Дата оплаты')->render(function (Order $order) {
                    return $order->paid_at ? $order->paid_at->format('d.m.Y H:i') : '-';
                }),
            ])->title('Основная информация'),

            Layout::legend('order', [
                Sight::make('delivery_type', 'Способ получения')->render(function (Order $order) {
                    if (!$order->delivery) {
                        return '<span class="badge bg-secondary">Не доставлено</span>';
                    }
                    $icon = $order->delivery->channel === 'telegram' ? '📱' : '📧';
                    return $icon . ' ' . $order->delivery->delivery_type;
                }),
                Sight::make('recipient', 'Получатель')->render(function (Order $order) {
                    if (!$order->delivery) {
                        return '-';
                    }
                    if ($order->delivery->channel === 'telegram' && $order->delivery->telegramUser) {
                        $user = $order->delivery->telegramUser;
                        $name = $user->full_name;
                        if ($user->username) {
                            $name .= ' (@' . $user->username . ')';
                        }
                        return $name;
                    } elseif ($order->delivery->channel === 'email') {
                        return $order->delivery->email ?? '-';
                    }
                    return '-';
                }),
                Sight::make('delivery_status', 'Статус доставки')->render(function (Order $order) {
                    if (!$order->delivery) {
                        return '-';
                    }
                    $statusNames = [
                        'pending' => '<span class="badge bg-warning">Ожидает</span>',
                        'sent' => '<span class="badge bg-info">Отправлено</span>',
                        'delivered' => '<span class="badge bg-success">Доставлено</span>',
                        'failed' => '<span class="badge bg-danger">Ошибка</span>',
                    ];
                    return $statusNames[$order->delivery->status] ?? $order->delivery->status;
                }),
            ])->title('Информация о доставке'),

            Layout::view('admin.order-images', ['order' => $this->order]),
        ];
    }
}
