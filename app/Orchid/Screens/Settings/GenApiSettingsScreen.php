<?php

namespace App\Orchid\Screens\Settings;

use App\Models\Setting;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class GenApiSettingsScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        $settings = Setting::getByGroup('image_generation');

        // Если настройки еще не созданы, используем значения по умолчанию
        if (empty($settings)) {
            $settings = $this->getDefaultSettings();
        }

        return [
            'settings' => $settings,
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Настройки генерации изображений';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Управление API сервисами для генерации изображений (GenAPI, OpenRouter)';
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
                ->icon('check')
                ->method('save'),
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
                Select::make('settings.active_service')
                    ->title('Активный сервис генерации')
                    ->options([
                        'genapi' => 'GenAPI',
                        'openrouter' => 'OpenRouter',
                    ])
                    ->help('Выберите сервис, который будет использоваться для генерации изображений')
                    ->required(),
            ]),

            Layout::rows([
                Input::make('settings.genapi_name')
                    ->title('Название сервиса')
                    ->value('GenAPI')
                    ->help('Отображаемое название сервиса GenAPI'),

                Input::make('settings.genapi_endpoint')
                    ->title('API Endpoint')
                    ->placeholder('https://api.gen-api.ru/api/v1/networks/gemini-flash-image')
                    ->help('URL эндпоинта API GenAPI'),

                Input::make('settings.genapi_api_key')
                    ->title('API Key')
                    ->type('password')
                    ->placeholder('Введите API ключ GenAPI')
                    ->help('Секретный ключ для доступа к GenAPI'),
            ])->title('Настройки GenAPI'),

            Layout::rows([
                Input::make('settings.openrouter_name')
                    ->title('Название сервиса')
                    ->value('OpenRouter')
                    ->help('Отображаемое название сервиса OpenRouter'),

                Input::make('settings.openrouter_endpoint')
                    ->title('API Endpoint')
                    ->placeholder('https://openrouter.ai/api/v1/chat/completions')
                    ->help('URL эндпоинта API OpenRouter'),

                Input::make('settings.openrouter_api_key')
                    ->title('API Key')
                    ->type('password')
                    ->placeholder('Введите API ключ OpenRouter')
                    ->help('Секретный ключ для доступа к OpenRouter'),

                Input::make('settings.openrouter_model')
                    ->title('Модель')
                    ->placeholder('google/gemini-2.5-flash-image')
                    ->help('Модель для генерации изображений (например: google/gemini-2.5-flash-image)'),
            ])->title('Настройки OpenRouter'),
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
        $request->validate([
            'settings.active_service' => 'required|in:genapi,openrouter',
        ]);

        $settings = $request->get('settings', []);

        // Сохраняем настройки в БД с группой 'image_generation'
        $settingsToSave = [
            'active_service' => [
                'value' => $settings['active_service'] ?? 'genapi',
                'group' => 'image_generation',
                'description' => 'Активный сервис для генерации изображений'
            ],
            'genapi_name' => [
                'value' => $settings['genapi_name'] ?? 'GenAPI',
                'group' => 'image_generation',
                'description' => 'Название сервиса GenAPI'
            ],
            'genapi_endpoint' => [
                'value' => $settings['genapi_endpoint'] ?? '',
                'group' => 'image_generation',
                'description' => 'API endpoint для GenAPI'
            ],
            'genapi_api_key' => [
                'value' => $settings['genapi_api_key'] ?? '',
                'group' => 'image_generation',
                'description' => 'API ключ для GenAPI'
            ],
            'openrouter_name' => [
                'value' => $settings['openrouter_name'] ?? 'OpenRouter',
                'group' => 'image_generation',
                'description' => 'Название сервиса OpenRouter'
            ],
            'openrouter_endpoint' => [
                'value' => $settings['openrouter_endpoint'] ?? '',
                'group' => 'image_generation',
                'description' => 'API endpoint для OpenRouter'
            ],
            'openrouter_api_key' => [
                'value' => $settings['openrouter_api_key'] ?? '',
                'group' => 'image_generation',
                'description' => 'API ключ для OpenRouter'
            ],
            'openrouter_model' => [
                'value' => $settings['openrouter_model'] ?? 'google/gemini-2.5-flash-image',
                'group' => 'image_generation',
                'description' => 'Модель для генерации изображений OpenRouter'
            ],
        ];

        Setting::setMultiple($settingsToSave);

        Toast::info('Настройки генерации изображений успешно сохранены');

        return redirect()->route('platform.settings.genapi');
    }

    /**
     * Get default settings
     *
     * @return array
     */
    private function getDefaultSettings(): array
    {
        return [
            'active_service' => env('USE_GENAPI_SERVICE', false) ? 'genapi' : 'openrouter',
            'genapi_name' => 'GenAPI',
            'genapi_endpoint' => env('GENAPI_ENDPOINT', 'https://api.gen-api.ru/api/v1/networks/gemini-flash-image'),
            'genapi_api_key' => env('GENAPI_API_KEY', ''),
            'openrouter_name' => 'OpenRouter',
            'openrouter_endpoint' => env('OPENROUTER_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'),
            'openrouter_api_key' => env('OPENROUTER_API_KEY', ''),
            'openrouter_model' => env('OPENROUTER_MODEL', 'google/gemini-2.5-flash-image'),
        ];
    }
}
