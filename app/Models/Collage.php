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
        // preview_path хранит массив ID загруженных файлов
        'preview_path' => 'array',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get content for table column.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->title;
    }
}
