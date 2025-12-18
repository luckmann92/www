<?php

namespace App\Orchid\Screens\Settings;

use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layout;
use Orchid\Support\Facades\Layout as LayoutComponent;
use Illuminate\Http\Request;

class MainSettingsScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        return [];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Настройки';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Категории настроек приложения';
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
                Link::make('Настройки GenAPI')
                    ->route('platform.settings.genapi')
                    ->icon('bs.cloud-arrow-up')
                    ->hr(),
                Link::make('Настройки платежей')
                    ->route('platform.settings.payment')
                    ->icon('bs.credit-card')
                    ->hr(),
                Link::make('Настройки Telegram')
                    ->route('platform.settings.telegram')
                    ->icon('bs.chat')
                    ->hr(),
            ]),
        ];
    }
}
