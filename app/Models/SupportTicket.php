<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Screen\AsSource;

class SupportTicket extends Model
{
    use AsSource;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'telegram_user_id',
        'order_id',
        'description',
        'status',
        'admin_response',
        'responded_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /**
     * Статусы обращений
     */
    const STATUS_NEW = 'new';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_CLOSED = 'closed';

    /**
     * Получить список статусов
     *
     * @return array
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_NEW => 'Новое',
            self::STATUS_IN_PROGRESS => 'В работе',
            self::STATUS_CLOSED => 'Закрыто',
        ];
    }

    /**
     * Получить название статуса
     *
     * @return string
     */
    public function getStatusNameAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * Получить пользователя Telegram
     */
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }

    /**
     * Получить связанный заказ
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Получить сообщения тикета
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at', 'asc');
    }

    /**
     * Добавить сообщение от пользователя
     */
    public function addUserMessage(string $message): TicketMessage
    {
        return $this->messages()->create([
            'sender_type' => TicketMessage::SENDER_USER,
            'message' => $message,
        ]);
    }

    /**
     * Добавить сообщение от администратора
     */
    public function addAdminMessage(string $message): TicketMessage
    {
        return $this->messages()->create([
            'sender_type' => TicketMessage::SENDER_ADMIN,
            'message' => $message,
        ]);
    }

    /**
     * Scope для получения новых обращений
     */
    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    /**
     * Scope для получения обращений в работе
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope для получения закрытых обращений
     */
    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Перевести обращение в работу
     */
    public function markAsInProgress(): bool
    {
        return $this->update(['status' => self::STATUS_IN_PROGRESS]);
    }

    /**
     * Закрыть обращение
     */
    public function close(string $adminResponse = null): bool
    {
        return $this->update([
            'status' => self::STATUS_CLOSED,
            'admin_response' => $adminResponse ?? $this->admin_response,
            'responded_at' => now(),
        ]);
    }
}
