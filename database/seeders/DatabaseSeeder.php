<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal minimum: akun admin/pengguna dan master unit + formasi jabatan (database/data/master_formasi.json).
     * Data pegawai tidak di-seed; masuk lewat impor Excel agar nama unit/jabatan mengikuti Master Unit.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            PositionUnitSeeder::class,
        ]);
    }
}
