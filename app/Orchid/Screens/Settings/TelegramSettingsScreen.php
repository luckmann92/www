<?php

namespace App\Orchid\Screens\Settings;

use App\Services\SettingsService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class TelegramSettingsScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        $settingsService = new SettingsService();
        return [
            'settings' => $settingsService->getAll(),
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Настройки Telegram';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Настройки Telegram бота';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): array
    {
        return [
            Button::make('Сохранить')
                ->method('save')
                ->icon('check'),
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
            Layout::rows([
                Input::make('settings.telegram_bot_token')
                    ->title('Telegram Bot Token')
                    ->placeholder('Введите токен бота')
                    ->type('password')
                    ->help('Токен вашего Telegram бота'),
            ]),
        ];
    }

    /**
     * Save settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request)
    {
        $settings = $request->get('settings', []);

        // Filter settings to only include the ones we want to save
        $filteredSettings = [
            'telegram_bot_token' => $settings['telegram_bot_token'] ?? null,
        ];

        $settingsService = new SettingsService();
        $settingsService->set(array_merge($settingsService->getAll(), $filteredSettings));

        $request->session()->flash('message', 'Настройки Telegram сохранены!');

        return redirect()->route('platform.main');
    }
}
