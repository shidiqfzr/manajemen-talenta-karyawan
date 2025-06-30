<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = [
            [
                'nik' => '13004521',
                'nama' => 'TRI INDAH SARY',
                'jabatan' => 'Asisten Akuntansi',
                'level' => 'test_dummy',
                'unit_kerja' => 'BAGIAN AKUNTANSI DAN KEUANGAN',
                'golongan_2024' => 'IIID/00',
                'tanggal_dalam_jabatan' => '2024-01-01',
                'tmt_unit_kerja' => '2010-01-01',
                'tempat_lahir' => 'Pontianak',
                'tanggal_lahir' => '1984-07-10',
                'tmt_bekerja' => '2010-01-01',
                'tanggal_diangkat_staf' => '2010-01-01',
                'susunan_keluarga' => 'L',
                'job_grader' => 11,
                'person_grade' => 12,
                'tanggal_mbt' => '2040-02-01',
                'tanggal_pensiun' => '2025-10-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Ekonomi',
                'sekolah' => 'Univ. Tanjungpura Pontianak',
                'foto' => 'photos/default.png'
            ],
            [
                'nik' => '13004837',
                'nama' => 'HERRY WAHYUDI',
                'jabatan' => 'Kepala Bagian',
                'level' => 'test_dummy',
                'unit_kerja' => 'BAGIAN AKUNTANSI DAN KEUANGAN',
                'golongan_2024' => 'IIID/06',
                'tanggal_dalam_jabatan' => '2024-01-01',
                'tmt_unit_kerja' => '2012-06-01',
                'tempat_lahir' => 'Tanjung Enim',
                'tanggal_lahir' => '1983-04-22',
                'tmt_bekerja' => '2012-06-01',
                'tanggal_diangkat_staf' => '2012-06-01',
                'susunan_keluarga' => 'K/2',
                'job_grader' => 15,
                'person_grade' => 12,
                'tanggal_mbt' => '2038-11-01',
                'tanggal_pensiun' => '2052-03-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => '-',
                'sekolah' => 'Univ. Tanjungpura Pontianak',
                'foto' => 'photos/default.png'
            ],
            [
                'nik' => '13002797',
                'nama' => 'TENGKU DIDI ZULKARNAEN',
                'jabatan' => 'Kepala Sub Bagian Keuangan & HPS',
                'level' => 'test_dummy',
                'unit_kerja' => 'BAGIAN AKUNTANSI DAN KEUANGAN',
                'golongan_2024' => 'IVB/06',
                'tanggal_dalam_jabatan' => '2024-01-01',
                'tmt_unit_kerja' => '2013-09-01',
                'tempat_lahir' => 'Medan',
                'tanggal_lahir' => '1974-03-11',
                'tmt_bekerja' => '1999-09-01',
                'tanggal_diangkat_staf' => '1999-09-01',
                'susunan_keluarga' => 'K/1',
                'job_grader' => 13,
                'person_grade' => 14,
                'tanggal_mbt' => '2029-10-01',
                'tanggal_pensiun' => '2041-05-01',
                'agama' => 'Katolik',
                'pendidikan_terakhir' => 'S1 Ekonomi',
                'sekolah' => 'Univ. Atma Jaya Yogyakarta',
                'foto' => 'photos/default.png'
            ],
            [
                'nik' => '13004857',
                'nama' => 'AGNES',
                'jabatan' => 'Asisten Asuransi',
                'level' => 'test_dummy',
                'unit_kerja' => 'BAGIAN AKUNTANSI DAN KEUANGAN',
                'golongan_2024' => 'IIIB/09',
                'tanggal_dalam_jabatan' => '2024-01-01',
                'tmt_unit_kerja' => '2014-07-21',
                'tempat_lahir' => 'Pontianak',
                'tanggal_lahir' => '1985-04-16',
                'tmt_bekerja' => '2012-06-01',
                'tanggal_diangkat_staf' => '2012-06-01',
                'susunan_keluarga' => 'L',
                'job_grader' => 11,
                'person_grade' => 11,
                'tanggal_mbt' => '2040-11-01',
                'tanggal_pensiun' => '2041-10-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Manajemen',
                'sekolah' => 'Sekolah Tinggi Ilmu Ekonomi Pancasetia Banjarmasin',
                'foto' => 'photos/default.png'
            ],
        ];

        foreach ($employees as $employee) {
            // Check if employee already exists before inserting
            Employee::firstOrCreate(
                ['nik' => $employee['nik']], 
                $employee
            );
        }
    }
}