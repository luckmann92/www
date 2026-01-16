<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Orchid\Screen\AsSource;

use App\Models\Order;

class Delivery extends Model
{
    use AsSource;
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
