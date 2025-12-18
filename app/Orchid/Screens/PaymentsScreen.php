<?php

namespace App\Orchid\Screens;

use App\Models\Payment;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layout;
use Orchid\Support\Facades\Layout as LayoutComponent;

class PaymentsScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        return [
            'payments' => Payment::with(['order'])->paginate(10),
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Платежи';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Список всех платежей';
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
            LayoutComponent::table('payments', [
                'id',
                'order_id',
                'method',
                'amount',
                'status',
                'paid_at',
                'created_at',
                'actions' => Button::make('Просмотр')
                    ->icon('eye')
                    ->method('view')
                    ->canSee(true),
            ]),
        ];
    }
}
