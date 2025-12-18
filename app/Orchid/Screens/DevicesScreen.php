<?php

namespace App\Orchid\Screens;

use App\Models\Device;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layout;
use Orchid\Support\Facades\Layout as LayoutComponent;

class DevicesScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        return [
            'devices' => Device::paginate(10),
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Киоски';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Управление устройствами ИИ-киоска';
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
            LayoutComponent::table('devices', [
                'id',
                'name',
                'serial',
                'status',
                'updated_at',
                'actions' => Button::make('Просмотр')
                    ->icon('eye')
                    ->method('view')
                    ->canSee(true),
            ]),
        ];
    }
}
