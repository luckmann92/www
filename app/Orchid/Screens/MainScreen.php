<?php

namespace App\Orchid\Screens;

use App\Models\Order;
use Carbon\Carbon;
use Orchid\Screen\Screen;
use App\Orchid\Layouts\OrderStatsFilters;
use Orchid\Support\Facades\Layout;
use Orchid\Screen\Layouts\Chart;

class MainScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Получаем даты из фильтра или используем диапазон за последний месяц по умолчанию
        $dateRange = request()->get('date_range');

        if ($dateRange && isset($dateRange['start']) && isset($dateRange['end'])) {
            $startDate = Carbon::parse($dateRange['start']);
            $endDate = Carbon::parse($dateRange['end'])->endOfDay();
        } else {
            $endDate = Carbon::now();
            $startDate = Carbon::now()->subMonth();
        }

        // Общее количество заказов
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();

        // Количество заказов по статусам
        $ordersByStatus = Order::selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('status')
            ->pluck('count', 'status');

        // Количество оплаченных заказов
        $paidOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->count();

        // Общая сумма оплат
        $totalRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('price');

        // График заказов по дням
        $ordersByDay = Order::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dates = $ordersByDay->pluck('date')->toArray();
        $counts = $ordersByDay->pluck('count')->toArray();

        // Подготовка данных для графиков
        $chartData = [
            [
                'name' => 'Заказы по дням',
                'values' => $counts,
                'labels' => $dates,
            ]
        ];

        // Подготовка данных для графика по статусам
        $statusLabels = array_keys($ordersByStatus->toArray());
        $statusValues = array_values($ordersByStatus->toArray());

        // Заменяем коды статусов на понятные названия
        $statusNames = [];
        foreach ($statusLabels as $status) {
            switch ($status) {
                case 'created':
                    $statusNames[] = 'Создан';
                    break;
                case 'paid':
                    $statusNames[] = 'Оплачен';
                    break;
                case 'ready_blurred':
                    $statusNames[] = 'Готов (размыт)';
                    break;
                case 'unlocked':
                    $statusNames[] = 'Разблокирован';
                    break;
                case 'delivered':
                    $statusNames[] = 'Доставлен';
                    break;
                default:
                    $statusNames[] = $status;
                    break;
            }
        }

        $statusChartData = [
            [
                'name' => 'Заказы по статусам',
                'values' => $statusValues,
                'labels' => $statusNames,
            ]
        ];

        // Подготовка данных для доходов по дням
        $revenueByDay = Order::selectRaw('DATE(created_at) as date, SUM(price) as revenue')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('paid_at')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $revenueDates = $revenueByDay->pluck('date')->toArray();
        $revenueValues = $revenueByDay->pluck('revenue')->toArray();

        $revenueData = [
            [
                'name' => 'Доход по дням',
                'values' => $revenueValues,
                'labels' => $revenueDates,
            ]
        ];

        return [
            'totalOrders' => $totalOrders,
            'paidOrders' => $paidOrders,
            'totalRevenue' => $totalRevenue,
            'chartData' => $chartData,
            'statusChartData' => $statusChartData,
            'revenueData' => $revenueData,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Главная';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Статистика заказов и оплат';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            \Orchid\Screen\Actions\Button::make('Применить фильтр')
                ->icon('filter')
                ->method('applyFilter'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @throws \Throwable
     *
     * @return string[]|\Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            OrderStatsFilters::class,

            Layout::columns([
                Layout::metrics([
                    'Всего заказов' => 'totalOrders',
                    'Оплаченных заказов' => 'paidOrders',
                    'Общий доход' => 'totalRevenue',
                ]),
            ]),

            Layout::columns([
                Layout::chart('chartData', 'Заказы по дням')
                    ->type('line')
                    ->height(300)
                    ->description('Количество заказов по дням за выбранный период'),

                Layout::chart('statusChartData', 'Статусы заказов')
                    ->type('pie')
                    ->height(300)
                    ->description('Распределение заказов по статусам'),
            ]),

            Layout::chart('revenueData', 'Доход по дням')
                ->type('bar')
                ->height(300)
                ->description('Доход по дням за выбранный период'),
        ];
    }

    /**
     * Apply filter to the statistics.
     */
    public function applyFilter()
    {
        $dateRange = request()->get('date_range');

        $params = [];
        if ($dateRange && isset($dateRange['start']) && isset($dateRange['end'])) {
            $params['date_range'] = $dateRange;
        }

        return redirect()->route('platform.main', $params);
    }
}
