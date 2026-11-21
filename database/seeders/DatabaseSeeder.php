<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal minimum: akun admin/pengguna dan master unit + formasi jabatan (database/data/master_formasi.json).
     * Data karyawan tidak di-seed; masuk lewat impor Excel agar nama unit/jabatan mengikuti Master Unit.
     * Untuk data contoh di mesin pengembangan jalankan `composer demo` (DemoEmployeeSeeder; menolak produksi).
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            PositionUnitSeeder::class,
        ]);
    }
}
