<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
    ];

    const CACHE_KEY = 'app_settings';
    const CACHE_TTL = 3600; // 1 час

    /**
     * Get setting value by key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $settings = self::getAllCached();
        return $settings[$key] ?? $default;
    }

    /**
     * Set setting value
     *
     * @param string $key
     * @param mixed $value
     * @param string $group
     * @param string|null $description
     * @return Setting
     */
    public static function set(string $key, $value, string $group = 'general', ?string $description = null): Setting
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'description' => $description,
            ]
        );

        self::clearCache();

        return $setting;
    }

    /**
     * Set multiple settings
     *
     * @param array $settings Array of ['key' => 'value'] or ['key' => ['value' => 'val', 'group' => 'grp', 'description' => 'desc']]
     * @param string $defaultGroup
     * @return void
     */
    public static function setMultiple(array $settings, string $defaultGroup = 'general'): void
    {
        foreach ($settings as $key => $data) {
            if (is_array($data)) {
                self::set(
                    $key,
                    $data['value'] ?? null,
                    $data['group'] ?? $defaultGroup,
                    $data['description'] ?? null
                );
            } else {
                self::set($key, $data, $defaultGroup);
            }
        }
    }

    /**
     * Get all settings cached
     *
     * @return array
     */
    public static function getAllCached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::all()->pluck('value', 'key')->toArray();
        });
    }

    /**
     * Get all settings by group
     *
     * @param string $group
     * @return array
     */
    public static function getByGroup(string $group): array
    {
        return self::where('group', $group)->pluck('value', 'key')->toArray();
    }

    /**
     * Clear settings cache
     *
     * @return void
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
