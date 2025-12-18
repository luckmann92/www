<?php

namespace Database\Factories;

use App\Models\Collage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CollageFactory extends Factory
{
    protected $model = Collage::class;

    public function definition()
    {
        return [
            'title' => $this->faker->sentence(3),
            'prompt' => $this->faker->sentence(10),
            'preview_path' => 'previews/' . $this->faker->image('public/storage/previews', 600, 400, 'abstract', false),
            'is_active' => true,
            'price' => $this->faker->numberBetween(100, 1000),
        ];
    }
}
