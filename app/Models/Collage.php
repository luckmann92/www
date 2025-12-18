<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collage extends Model
{
    protected $fillable = [
        'title',
        'prompt',
        'preview_path',
        'is_active',
        'price',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'integer',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
