<?php

namespace App\Orchid\Screens;

use App\Models\Collage;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Fields\Upload;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Layout;
use Orchid\Support\Facades\Layout as LayoutComponent;

class CollagesScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        return [
            'collages' => Collage::paginate(10),
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Шаблоны коллажей';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Управление шаблонами для ИИ-фото';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): array
    {
        return [
            Link::make('Создать шаблон')
                ->icon('plus')
                ->route('platform.collage.create'),
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
            LayoutComponent::table('collages', [
                'id',
                'title',
                'price',
                'is_active' => 'is_active',
                'updated_at' => 'updated_at',
                'actions' => Button::make('Редактировать')
                    ->icon('pencil')
                    ->method('edit')
                    ->canSee(true),
            ]),
        ];
    }
}
