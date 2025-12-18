<?php

namespace App\Orchid\Screens;

use App\Models\Order;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layout;
use Orchid\Support\Facades\Layout as LayoutComponent;

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
            'orders' => Order::with(['session', 'collage'])->paginate(10),
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
            LayoutComponent::table('orders', [
                'id',
                'session_id',
                'collage.title',
                'price',
                'status',
                'created_at',
                'actions' => Button::make('Просмотр')
                    ->icon('eye')
                    ->method('view')
                    ->canSee(true),
            ]),
        ];
    }
}
