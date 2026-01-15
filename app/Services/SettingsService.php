<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    const CACHE_KEY = 'app_settings';

    /**
     * Get all settings
     *
     * @return array
     */
    public function getAll(): array
    {
        // Пытаемся получить настройки из БД через модель Setting
        $dbSettings = Setting::getAllCached();

        // Если в БД нет настроек, используем значения по умолчанию из .env
        if (empty($dbSettings)) {
            return Cache::get(self::CACHE_KEY, $this->getDefaultSettings());
        }

        // Объединяем настройки из БД с настройками по умолчанию (БД имеет приоритет)
        return array_merge($this->getDefaultSettings(), $dbSettings);
    }

    /**
     * Get a specific setting
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        // Сначала пробуем получить из БД
        $value = Setting::get($key);

        if ($value !== null) {
            return $value;
        }

        // Если нет в БД, проверяем кэш и настройки по умолчанию
        $settings = $this->getAll();
        return $settings[$key] ?? $default;
    }

    /**
     * Set settings (для обратной совместимости, сохраняет в кэш)
     *
     * @param array $settings
     * @return void
     */
    public function set(array $settings): void
    {
        // Get current settings and merge with new ones
        $currentSettings = $this->getAll();
        $mergedSettings = array_merge($currentSettings, $settings);
        Cache::put(self::CACHE_KEY, $mergedSettings);
    }

    /**
     * Set settings to database
     *
     * @param array $settings
     * @param string $group
     * @return void
     */
    public function setToDatabase(array $settings, string $group = 'general'): void
    {
        Setting::setMultiple($settings, $group);
    }

    /**
     * Get default settings
     *
     * @return array
     */
    private function getDefaultSettings(): array
    {
        return [
            'openrouter_api_key' => env('OPENROUTER_API_KEY', ''),
            'openrouter_endpoint' => env('OPENROUTER_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'),
            'genapi_api_key' => env('GENAPI_API_KEY', ''),
            'genapi_endpoint' => env('GENAPI_ENDPOINT', 'https://api.gen-api.ru/api/v1/networks/gemini-flash-image'),
            'use_genapi_service' => env('USE_GENAPI_SERVICE', false),
            'active_service' => env('USE_GENAPI_SERVICE', false) ? 'genapi' : 'openrouter',
            'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
            'payment_system' => env('PAYMENT_SYSTEM', 'custom'),
            'payment_provider_key' => env('PAYMENT_PROVIDER_KEY', ''),
            'payment_provider_endpoint' => env('PAYMENT_PROVIDER_BASE_URL', 'https://api.payment.example.com'),
            'yookassa_shop_id' => env('YOOKASSA_SHOP_ID', ''),
            'yookassa_secret_key' => env('YOOKASSA_SECRET_KEY', ''),
            'yookassa_api_key' => env('YOOKASSA_API_KEY', ''),
            'alfabank_username' => env('ALFABANK_USERNAME', ''),
            'alfabank_password' => env('ALFABANK_PASSWORD', ''),
            'alfabank_base_url' => env('ALFABANK_BASE_URL', 'https://alfa.rbsuat.com/payment/rest'),
            'order_price' => env('ORDER_PRICE', 250),
            'photo_ttl_hours' => env('PHOTO_TTL_HOURS', 24),
        ];
    }
}
