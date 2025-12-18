<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    protected $fillable = [
        'session_id',
        'type',
        'path',
        'blur_level',
        'status',
    ];

    protected $casts = [
        'blur_level' => 'integer',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}
