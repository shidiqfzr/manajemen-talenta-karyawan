<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Asisten ' . $this->faker->word(),
            'rm_level' => 'RM-3',
            'bidang' => 'TAN',
            'is_active' => true,
            'is_overflow' => false,
            'is_diperban' => false,
        ];
    }
}
