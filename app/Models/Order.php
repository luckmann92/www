<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Orchid\Screen\AsSource;

class Order extends Model
{
    use AsSource;
    /**
     * The "booting" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }

            if (empty($model->code)) {
                $model->code = static::generateUniqueCode();
            }
        });
    }

    /**
     * Generate a unique code in the format XXX-XXX
     */
    public static function generateUniqueCode(): string
    {
        do {
            $part1 = rand(100, 999);
            $part2 = rand(100, 999);
            $code = "{$part1}-{$part2}";
            $existing = static::where('code', $code)->first();
        } while ($existing);

        return $code;
    }

    protected $fillable = [
        'session_id',
        'collage_id',
        'price',
        'status',
        'paid_at',
        'uuid',
    ];

    protected $casts = [
        'price' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function collage()
    {
        return $this->belongsTo(Collage::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }
}
