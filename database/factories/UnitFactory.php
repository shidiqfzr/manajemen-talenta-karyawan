<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Kebun ' . $this->faker->city(),
            'wilayah' => 'Wilayah Kalimantan Barat',
            'is_active' => true,
        ];
    }
}
