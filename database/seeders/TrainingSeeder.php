<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Training;

class TrainingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $trainings = [
            [
                'judul' => 'Training Akuntansi Dasar',
                'jenis' => 'Akuntansi',
                'metode' => 'Offline',
                'tanggal_mulai' => '2025-01-10',
                'tanggal_akhir' => '2025-01-15',
                'biaya_tiket_peserta' => 100000,
                'biaya_pelatihan' => 1500000,
                'penyelenggara' => 'PT. Pelatihan Akuntansi',
                'nomor_surat' => 'PTA/010/2025',
                'tanggal_surat' => '2025-01-10',
                'keterangan' => 'Non Agrowalet',
                'bidang_pelatihan' => 'Akuntansi',
                'jam_belajar_per_hari' => 5,
                'jumlah_man_hours' => 0,
            ],
            [
                'judul' => 'Pelatihan Manajemen Keuangan',
                'jenis' => 'Manajemen',
                'metode' => 'Hybrid',
                'tanggal_mulai' => '2025-02-10',
                'tanggal_akhir' => '2025-02-13',
                'biaya_tiket_peserta' => 150000,
                'biaya_pelatihan' => 2000000,
                'penyelenggara' => 'PT. Pelatihan Manajemen',
                'nomor_surat' => 'PTM/010/2025',
                'tanggal_surat' => '2025-02-10',
                'keterangan' => 'Non Agrowalet',
                'bidang_pelatihan' => 'Keuangan',
                'jam_belajar_per_hari' => 8,
                'jumlah_man_hours' => 0,
            ],
        ];

        foreach ($trainings as $training) {
            Training::create($training);
        }
    }
}
