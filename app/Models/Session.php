<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    protected $fillable = [
        'id',
        'device_id',
        'status',
        'started_at',
        'finished_at',
        'payload',
        'ip_address',
        'user_agent',
        'last_activity',
        'user_id',
    ];

    protected $casts = [
        'id' => 'string',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_activity' => 'integer',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->payload)) {
                $model->payload = '';
            }

            if (empty($model->last_activity)) {
                $model->last_activity = time();
            }
        });
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
