<?php

namespace App\Orchid\Screens\Settings;

use App\Services\SettingsService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class GenApiSettingsScreen extends Screen
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
        return 'Настройки GenAPI';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Настройки интеграции с сервисом GenAPI';
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
                Input::make('settings.genapi_api_key')
                    ->title('GenAPI API ключ')
                    ->placeholder('Введите ключ GenAPI')
                    ->type('password')
                    ->help('Ваш API-ключ от GenAPI'),
                Input::make('settings.genapi_endpoint')
                    ->title('GenAPI Endpoint')
                    ->placeholder('https://api.gen-api.ru/api/v1/networks/gemini-flash-image')
                    ->help('URL для API GenAPI'),
                Select::make('settings.use_genapi_service')
                    ->title('Использовать GenAPI')
                    ->options([
                        '0' => 'Нет',
                        '1' => 'Да'
                    ])
                    ->help('Выберите, использовать ли GenAPI для генерации изображений'),
                Input::make('settings.openrouter_api_key')
                    ->title('OpenRouter API ключ')
                    ->placeholder('Введите ключ OpenRouter')
                    ->type('password')
                    ->help('Резервный API-ключ OpenRouter'),
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
            'genapi_api_key' => $settings['genapi_api_key'] ?? null,
            'genapi_endpoint' => $settings['genapi_endpoint'] ?? null,
            'use_genapi_service' => (bool)($settings['use_genapi_service'] ?? false),
            'openrouter_api_key' => $settings['openrouter_api_key'] ?? null,
        ];

        $settingsService = new SettingsService();
        $settingsService->set(array_merge($settingsService->getAll(), $filteredSettings));

        $request->session()->flash('message', 'Настройки GenAPI сохранены!');

        return redirect()->route('platform.main');
    }
}
