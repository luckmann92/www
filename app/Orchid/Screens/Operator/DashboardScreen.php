<?php

namespace App\Orchid\Screens\Operator;

use App\Models\Order;
use App\Models\Delivery;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout as LayoutComponent;

class DashboardScreen extends Screen
{
    /**
     * Display name of the screen.
     */
    public function name(): string
    {
        return 'Operator Dashboard';
    }

    /**
     * Query data for the screen.
     * Aggregates basic KPIs for the operator.
     */
    public function query(): array
    {
        $totalOrders = Order::query()->count();
        $paidOrders = Order::query()->where('status', 'paid')->count();
        $readyBlurred = Order::query()->where('status', 'ready_blurred')->count();
        $delivered = Delivery::query()->where('status', 'delivered')->count();

        return [
            'orders' => $totalOrders,
            'paid' => $paidOrders,
            'blurred' => $readyBlurred,
            'delivered' => $delivered,
        ];
    }

    /**
     * Layouts for the screen.
     * Realistic overview using a single card with KPI lines.
     */
    public function layout(): array
    {
        $stats = $this->query();

        return [
            LayoutComponent::view('operator.dashboard', ['stats' => $stats])
        ];
    }
}
