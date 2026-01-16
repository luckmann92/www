<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Orchid\Screen\AsSource;

class Delivery extends Model
{
    use AsSource;

    protected $fillable = [
        'order_id',
        'telegram_user_id',
        'channel',
        'meta',
        'status',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function telegramUser()
    {
        return $this->belongsTo(TelegramUser::class);
    }

    /**
     * Получить email получателя (если доставка через email)
     */
    public function getEmailAttribute(): ?string
    {
        if ($this->channel === 'email') {
            return $this->meta['to'] ?? null;
        }
        return null;
    }

    /**
     * Получить тип доставки для отображения
     */
    public function getDeliveryTypeAttribute(): string
    {
        return match ($this->channel) {
            'email' => 'Email',
            'telegram' => 'Telegram',
            default => $this->channel,
        };
    }
}
