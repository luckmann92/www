<?php

namespace App\Orchid\Screens;

use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layout;
use Orchid\Support\Facades\Layout as LayoutComponent;
use Illuminate\Http\Request;

class SettingsScreen extends Screen
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
        return 'Глобальные настройки приложения';
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
            LayoutComponent::rows([
                Input::make('settings.openrouter_api_key')
                    ->title('OpenRouter API ключ')
                    ->placeholder('Введите ключ OpenRouter')
                    ->type('password'),
                Input::make('settings.genapi_api_key')
                    ->title('GenAPI API ключ')
                    ->placeholder('Введите ключ GenAPI')
                    ->type('password'),
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
                    ->help('Выберите, какой сервис использовать для генерации изображений'),
                Input::make('settings.telegram_bot_token')
                    ->title('Telegram Bot Token')
                    ->placeholder('Введите токен бота')
                    ->type('password'),
                Input::make('settings.payment_provider_key')
                    ->title('Ключ платежного провайдера')
                    ->placeholder('Введите ключ')
                    ->type('password'),
                Input::make('settings.order_price')
                    ->title('Цена заказа (₽)')
                    ->placeholder('250')
                    ->type('number'),
                Input::make('settings.photo_ttl_hours')
                    ->title('Время жизни фото (часы)')
                    ->placeholder('24')
                    ->type('number'),
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
            'openrouter_api_key' => $settings['openrouter_api_key'] ?? null,
            'genapi_api_key' => $settings['genapi_api_key'] ?? null,
            'genapi_endpoint' => $settings['genapi_endpoint'] ?? null,
            'use_genapi_service' => (bool)($settings['use_genapi_service'] ?? false),
            'telegram_bot_token' => $settings['telegram_bot_token'] ?? null,
            'payment_provider_key' => $settings['payment_provider_key'] ?? null,
            'order_price' => (int)($settings['order_price'] ?? 250),
            'photo_ttl_hours' => (int)($settings['photo_ttl_hours'] ?? 24),
        ];

        $settingsService = new SettingsService();
        $settingsService->set($filteredSettings);

        $request->session()->flash('message', 'Настройки сохранены!');

        return redirect()->route('platform.main');
    }
}
