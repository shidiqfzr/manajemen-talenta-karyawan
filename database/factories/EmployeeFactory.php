<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nik' => $this->faker->unique()->numerify('########'),
            'nama' => $this->faker->name(),
            'jabatan' => 'Asisten ' . $this->faker->word(),
            'unit_kerja' => 'Kebun ' . $this->faker->word(),
            'level' => 'Karpim',
            'golongan' => 'IIIB/01',
            'pendidikan_terakhir' => 'S1',
            'status_karyawan' => 'Tetap',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'tanggal_lahir' => $this->faker->date(),
            'tmt_bekerja' => $this->faker->date(),
            'tanggal_dalam_jabatan' => $this->faker->date(),
            'tmt_unit_kerja' => $this->faker->date(),
        ];
    }
}
