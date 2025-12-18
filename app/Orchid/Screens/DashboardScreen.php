<?php

namespace App\Orchid\Screens;

use App\Orchid\Layouts\Examples\ChartLineExample;
use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemMenu;
use Orchid\Screen\Screen;
use Orchid\Screen\Layout;
use Orchid\Screen\Layouts\Chart;
use Orchid\Screen\Layouts\Rows;
use Orchid\Screen\Layouts\Tabs;
use Orchid\Screen\Layouts\View;
use Orchid\Screen\Layouts\Wrapper;
use Orchid\Support\Facades\Layout as LayoutComponent;

class DashboardScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        // Example data for charts
        $orderData = [
            'labels' => ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн'],
            'datasets' => [
                [
                    'label' => 'Заказы',
                    'data' => [37, 48, 40, 19, 86, 27],
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                ],
            ],
        ];

        $revenueData = [
            'labels' => ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн'],
            'datasets' => [
                [
                    'label' => 'Выручка (₽)',
                    'data' => [12500, 18000, 15000, 9500, 25000, 13500],
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                ],
            ],
        ];

        return [
            'orderData' => $orderData,
            'revenueData' => $revenueData,
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Панель управления';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Статистика и управление ИИ-киоском';
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
            LayoutComponent::metrics([
                'Всего заказов' => 'orderData.datasets.0.data',
                'Выручка (₽)' => 'revenueData.datasets.0.data',
                'Среднее время генерации (мин)' => '15',
                'Ошибки' => '5',
            ]),
            LayoutComponent::columns([
                Chart::make('orderData')
                    ->title('Заказы за 6 месяцев')
                    ->type('line'),
                Chart::make('revenueData')
                    ->title('Выручка за 6 месяцев')
                    ->type('bar'),
            ]),
        ];
    }
}
