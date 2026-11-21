<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal minimum: akun admin/pengguna dan master unit + formasi jabatan (database/data/master_formasi.json).
     * Data karyawan tidak di-seed; masuk lewat impor Excel agar nama unit/jabatan mengikuti Master Unit.
     * Untuk data contoh di mesin pengembangan jalankan `composer demo` (DemoEmployeeSeeder; menolak produksi).
     *
     * Master struktur hanya di-seed sekali (DOMAIN.md §3.6): menjalankan ulang tidak boleh menimpa kuota atau menulis
     * ulang catatan SK baseline pada basis data yang sudah berubah sejak itu (§3.7). Akun bersifat idempoten.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);

        if (! Unit::query()->exists()) {
            $this->call(PositionUnitSeeder::class);
        }
    }
}
