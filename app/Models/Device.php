<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'name',
        'serial',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function sessions()
    {
        return $this->hasMany(Session::class);
    }
}
