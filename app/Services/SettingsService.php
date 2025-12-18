<?php

namespace App\Services;

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
        return Cache::get(self::CACHE_KEY, $this->getDefaultSettings());
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
        $settings = $this->getAll();
        return $settings[$key] ?? $default;
    }

    /**
     * Set settings
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
     * Get default settings
     *
     * @return array
     */
    private function getDefaultSettings(): array
    {
        return [
            'openrouter_api_key' => env('OPENROUTER_API_KEY', ''),
            'genapi_api_key' => env('GENAPI_API_KEY', ''),
            'genapi_endpoint' => env('GENAPI_ENDPOINT', 'https://api.gen-api.ru/api/v1/networks/gemini-flash-image'),
            'use_genapi_service' => env('USE_GENAPI_SERVICE', false),
            'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
            'payment_system' => env('PAYMENT_SYSTEM', 'custom'),
            'payment_provider_key' => env('PAYMENT_PROVIDER_KEY', ''),
            'payment_provider_endpoint' => env('PAYMENT_PROVIDER_BASE_URL', 'https://api.payment.example.com'),
            'yookassa_shop_id' => env('YOOKASSA_SHOP_ID', ''),
            'yookassa_secret_key' => env('YOOKASSA_SECRET_KEY', ''),
            'yookassa_api_key' => env('YOOKASSA_API_KEY', ''),
            'order_price' => env('ORDER_PRICE', 250),
            'photo_ttl_hours' => env('PHOTO_TTL_HOURS', 24),
        ];
    }
}
