<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'session_id',
        'collage_id',
        'price',
        'status',
        'paid_at',
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
