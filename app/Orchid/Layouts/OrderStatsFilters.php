<?php

namespace App\Orchid\Layouts;

use Orchid\Screen\Field;
use Orchid\Screen\Layouts\Rows;
use Orchid\Screen\Fields\DateRange;

class OrderStatsFilters extends Rows
{
    /**
     * Get the fields elements to be rendered.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            DateRange::make('date_range')
                ->title('Период')
                ->placeholder('Выберите период')
                ->help('Выберите диапазон дат для отображения статистики'),
        ];
    }
}
