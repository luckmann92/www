<?php

namespace Database\Seeders;

use App\Models\Collage;
use Illuminate\Database\Seeder;

class CollagesSeeder extends Seeder
{
    public function run()
    {
        $collages = [
            [
                'title' => 'Винтажный стиль',
                'prompt' => 'A vintage-style photo collage with sepia tones and classic elements',
                'preview_path' => 'previews/vintage.jpg',
                'is_active' => true,
                'price' => 300,
            ],
            [
                'title' => 'Модерн',
                'prompt' => 'A modern-style photo collage with geometric patterns and bold colors',
                'preview_path' => 'previews/modern.jpg',
                'is_active' => true,
                'price' => 400,
            ],
            [
                'title' => 'Минимализм',
                'prompt' => 'A minimalist photo collage with clean lines and subtle colors',
                'preview_path' => 'previews/minimalist.jpg',
                'is_active' => true,
                'price' => 350,
            ],
            [
                'title' => 'Классика',
                'prompt' => 'A classic photo collage with elegant framing and timeless elements',
                'preview_path' => 'previews/classic.jpg',
                'is_active' => true,
                'price' => 250,
            ],
        ];

        foreach ($collages as $collage) {
            Collage::create($collage);
        }
    }
}
