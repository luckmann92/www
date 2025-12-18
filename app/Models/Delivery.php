<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Order;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
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
}
