<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'support_ticket_id',
        'sender_type',
        'message',
    ];

    /**
     * Типы отправителей
     */
    const SENDER_USER = 'user';
    const SENDER_ADMIN = 'admin';

    /**
     * Получить тикет
     */
    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    /**
     * Проверить, является ли отправитель пользователем
     */
    public function isFromUser(): bool
    {
        return $this->sender_type === self::SENDER_USER;
    }

    /**
     * Проверить, является ли отправитель администратором
     */
    public function isFromAdmin(): bool
    {
        return $this->sender_type === self::SENDER_ADMIN;
    }
}
