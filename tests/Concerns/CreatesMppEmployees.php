<?php

namespace Tests\Concerns;

use App\Models\Employee;
use App\Models\Position;

/**
 * Karyawan contoh untuk pengujian MPP.
 */
trait CreatesMppEmployees
{
    /**
     * Staf RM-3 bidang KEU (heuristik "keu"). Tanggal dalam jabatan mengikuti tanggal mulai bekerja, sehingga
     * riwayat penempatan pertama yang ditulis EmployeeObserver bertanggal sama seperti data nyata.
     */
    protected function makeAsistenKeuangan(string $nik, array $overrides = []): Employee
    {
        $overrides += ['tanggal_dalam_jabatan' => $overrides['tmt_bekerja'] ?? '2020-01-01'];

        Position::firstOrCreate(
            ['nama' => 'Asisten Keuangan Uji'],
            ['rm_level' => 'RM-3', 'is_overflow' => false, 'is_active' => true]
        );

        return Employee::create(array_merge([
            'nik' => $nik,
            'nama' => 'Asisten '.$nik,
            'jabatan' => 'Asisten Keuangan Uji',
            'unit_kerja' => 'Bagian Keuangan Uji',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'tmt_bekerja' => '2020-01-01',
            'tanggal_lahir' => '1995-01-10',
            'status_penugasan' => 'Definitif',
        ], $overrides));
    }
}
