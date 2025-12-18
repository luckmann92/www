<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Order;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'method',
        'amount',
        'status',
        'txn_id',
        'paid_at',
        'payment_provider_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
