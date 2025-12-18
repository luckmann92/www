<?php

namespace App\Orchid\Screens\Settings;

use App\Services\SettingsService;
use Illuminate\Http\Request;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layout;
use Orchid\Screen\Layouts\Tabs;
use Orchid\Support\Facades\Layout as LayoutComponent;

class PaymentSettingsScreen extends Screen
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
        return 'Настройки платежей';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Настройки платежных систем';
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
            LayoutComponent::tabs([
                'Общие' => [
                    LayoutComponent::rows([
                        Select::make('settings.payment_system')
                            ->title('Платежная система')
                            ->options([
                                'yookassa' => 'Yookassa',
                                'custom' => 'Другая система',
                            ])
                            ->help('Выберите платежную систему'),
                        Input::make('settings.order_price')
                            ->title('Цена заказа (₽)')
                            ->placeholder('250')
                            ->type('number')
                            ->help('Стоимость одного заказа в рублях'),
                        Input::make('settings.photo_ttl_hours')
                            ->title('Время жизни фото (часы)')
                            ->placeholder('24')
                            ->type('number')
                            ->help('Время, в течение которого фото остаются доступными'),
                    ]),
                ],
                'Yookassa' => [
                    LayoutComponent::rows([
                        Input::make('settings.yookassa_shop_id')
                            ->title('Shop ID')
                            ->placeholder('Введите Shop ID')
                            ->help('Идентификатор магазина в Yookassa'),
                        Input::make('settings.yookassa_secret_key')
                            ->title('Секретный ключ')
                            ->placeholder('Введите секретный ключ')
                            ->type('password')
                            ->help('Секретный ключ для доступа к API Yookassa'),
                        Input::make('settings.yookassa_api_key')
                            ->title('API ключ')
                            ->placeholder('Введите API ключ')
                            ->type('password')
                            ->help('API ключ для доступа к API Yookassa'),
                    ]),
                ],
                'Другая система' => [
                    LayoutComponent::rows([
                        Input::make('settings.payment_provider_key')
                            ->title('Ключ платежного провайдера')
                            ->placeholder('Введите ключ')
                            ->type('password')
                            ->help('Ключ для интеграции с платежным провайдером'),
                        Input::make('settings.payment_provider_endpoint')
                            ->title('Endpoint платежного провайдера')
                            ->placeholder('https://api.payment-provider.com')
                            ->help('URL для интеграции с платежным провайдером'),
                    ]),
                ],
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
            'payment_system' => $settings['payment_system'] ?? 'custom',
            'yookassa_shop_id' => $settings['yookassa_shop_id'] ?? null,
            'yookassa_secret_key' => $settings['yookassa_secret_key'] ?? null,
            'yookassa_api_key' => $settings['yookassa_api_key'] ?? null,
            'payment_provider_key' => $settings['payment_provider_key'] ?? null,
            'payment_provider_endpoint' => $settings['payment_provider_endpoint'] ?? null,
            'order_price' => (int)($settings['order_price'] ?? 250),
            'photo_ttl_hours' => (int)($settings['photo_ttl_hours'] ?? 24),
        ];

        $settingsService = new SettingsService();
        $settingsService->set($filteredSettings);

        $request->session()->flash('message', 'Настройки платежей сохранены!');

        return redirect()->route('platform.settings.payment');
    }
}
