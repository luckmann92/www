<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Screen\AsSource;

class TelegramUser extends Model
{
    use AsSource;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'phone',
        'language_code',
        'last_interaction_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'telegram_id' => 'integer',
        'last_interaction_at' => 'datetime',
    ];

    /**
     * Получить обращения в поддержку пользователя
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Получить или создать пользователя Telegram по данным
     *
     * @param array $telegramData
     * @return TelegramUser
     */
    public static function createOrUpdate(array $telegramData): TelegramUser
    {
        return self::updateOrCreate(
            ['telegram_id' => $telegramData['id']],
            [
                'username' => $telegramData['username'] ?? null,
                'first_name' => $telegramData['first_name'] ?? null,
                'last_name' => $telegramData['last_name'] ?? null,
                'language_code' => $telegramData['language_code'] ?? null,
                'last_interaction_at' => now(),
            ]
        );
    }

    /**
     * Обновить время последнего взаимодействия
     */
    public function touch($attribute = null): bool
    {
        $this->last_interaction_at = now();
        return parent::touch($attribute);
    }

    /**
     * Получить полное имя пользователя
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([$this->first_name, $this->last_name]);
        return implode(' ', $parts) ?: ($this->username ? '@' . $this->username : 'Пользователь #' . $this->telegram_id);
    }
}
