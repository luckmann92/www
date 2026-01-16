<?php

namespace App\Orchid\Screens\Settings;

use App\Models\Setting;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class TelegramSettingsScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        $settings = Setting::getByGroup('telegram');

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
        return 'Настройки Telegram';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Настройки интеграции с Telegram ботом';
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
                Input::make('settings.telegram_bot_name')
                    ->title('Название бота')
                    ->placeholder('Введите название бота')
                    ->help('Отображаемое название Telegram бота'),

                Input::make('settings.telegram_bot_username')
                    ->title('Username бота')
                    ->placeholder('@your_bot')
                    ->help('Username вашего Telegram бота (например, @MyPhotoBot)'),

                Input::make('settings.telegram_bot_token')
                    ->title('Bot Token')
                    ->placeholder('Введите токен бота')
                    ->type('password')
                    ->help('API токен вашего Telegram бота')
                    ->required(),
            ])->title('Настройки Telegram бота'),

            Layout::rows([
                TextArea::make('settings.telegram_support_users')
                    ->title('Операторы техподдержки')
                    ->placeholder("@username1\n@username2\n@username3")
                    ->rows(5)
                    ->help('Укажите username операторов техподдержки (по одному на строку). Этим пользователям будут пересылаться обращения в поддержку и они смогут отвечать на них через Telegram.'),
            ])->title('Техническая поддержка'),
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
            'settings.telegram_bot_token' => 'required|string',
        ]);

        $settings = $request->get('settings', []);

        // Сохраняем настройки в БД с группой 'telegram'
        $settingsToSave = [
            'telegram_bot_name' => [
                'value' => $settings['telegram_bot_name'] ?? '',
                'group' => 'telegram',
                'description' => 'Название Telegram бота'
            ],
            'telegram_bot_username' => [
                'value' => $settings['telegram_bot_username'] ?? '',
                'group' => 'telegram',
                'description' => 'Username Telegram бота'
            ],
            'telegram_bot_token' => [
                'value' => $settings['telegram_bot_token'] ?? '',
                'group' => 'telegram',
                'description' => 'API токен Telegram бота'
            ],
            'telegram_support_users' => [
                'value' => $settings['telegram_support_users'] ?? '',
                'group' => 'telegram',
                'description' => 'Username операторов техподдержки (по одному на строку)'
            ],
        ];

        Setting::setMultiple($settingsToSave);

        Toast::info('Настройки Telegram успешно сохранены');

        return redirect()->route('platform.settings.telegram');
    }

    /**
     * Get default settings
     *
     * @return array
     */
    private function getDefaultSettings(): array
    {
        return [
            'telegram_bot_name' => '',
            'telegram_bot_username' => '',
            'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
            'telegram_support_users' => '',
        ];
    }
}
