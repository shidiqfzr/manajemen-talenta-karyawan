<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\JobHistory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RealisticMutationSeeder extends Seeder
{
    /**
     * Run realistic mutation dummy data seeder.
     */
    public function run(): void
    {
        // 1. Ensure a diverse set of employees across multiple units
        $employeesData = [
            // --- KANTOR DIREKSI: SDM & SISTEM MANAJEMEN ---
            [
                'nik' => '13004628',
                'nama' => 'DONNY USMAN',
                'jabatan' => 'Pj. Kepala Bagian',
                'level' => 'Karpim',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'golongan' => 'IVA/05',
                'tanggal_dalam_jabatan' => '2023-03-01',
                'tmt_unit_kerja' => '2020-01-01',
                'tempat_lahir' => 'Medan',
                'tanggal_lahir' => '1979-05-14',
                'tmt_bekerja' => '2004-03-01',
                'tanggal_diangkat_staf' => '2008-01-01',
                'susunan_keluarga' => 'K/2',
                'job_grade' => 16,
                'person_grade' => 15,
                'tanggal_mbt' => '2034-05-01',
                'tanggal_pensiun' => '2035-06-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S2 Manajemen SDM',
                'sekolah' => 'Universitas Sumatera Utara',
                'foto' => 'photos/default.png',
            ],
            [
                'nik' => '13002807',
                'nama' => 'WINNA FRIEDIANY',
                'jabatan' => 'Kepala Sub Bagian Pengembangan SDM & Talenta',
                'level' => 'Karpim',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'golongan' => 'IVB/00',
                'tanggal_dalam_jabatan' => '2024-01-15',
                'tmt_unit_kerja' => '2018-05-01',
                'tempat_lahir' => 'Pekanbaru',
                'tanggal_lahir' => '1982-11-20',
                'tmt_bekerja' => '2006-06-01',
                'tanggal_diangkat_staf' => '2010-01-01',
                'susunan_keluarga' => 'K/1',
                'job_grade' => 14,
                'person_grade' => 14,
                'tanggal_mbt' => '2037-11-01',
                'tanggal_pensiun' => '2038-12-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Psikologi',
                'sekolah' => 'Universitas Riau',
                'foto' => 'photos/default.png',
            ],
            [
                'nik' => '13004853',
                'nama' => 'DJOKO PURWANTO',
                'jabatan' => 'Kepala Sub Bagian Kesejahteraan & Hubungan Industrial',
                'level' => 'Karpim',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'golongan' => 'IIID/05',
                'tanggal_dalam_jabatan' => '2024-02-01',
                'tmt_unit_kerja' => '2019-08-01',
                'tempat_lahir' => 'Solo',
                'tanggal_lahir' => '1985-08-12',
                'tmt_bekerja' => '2011-04-01',
                'tanggal_diangkat_staf' => '2013-01-01',
                'susunan_keluarga' => 'K/2',
                'job_grade' => 13,
                'person_grade' => 13,
                'tanggal_mbt' => '2040-08-01',
                'tanggal_pensiun' => '2041-09-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Hukum',
                'sekolah' => 'Universitas Sebelas Maret',
                'foto' => 'photos/default.png',
            ],
            [
                'nik' => '13004867',
                'nama' => 'ADHITYA YUDA ANGGARA',
                'jabatan' => 'Asisten Manajemen Kinerja SDM',
                'level' => 'Karpim',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'golongan' => 'IIIC/02',
                'tanggal_dalam_jabatan' => '2023-06-01',
                'tmt_unit_kerja' => '2021-03-01',
                'tempat_lahir' => 'Yogyakarta',
                'tanggal_lahir' => '1989-02-17',
                'tmt_bekerja' => '2014-08-01',
                'tanggal_diangkat_staf' => '2016-01-01',
                'susunan_keluarga' => 'K/1',
                'job_grade' => 11,
                'person_grade' => 12,
                'tanggal_mbt' => '2044-02-01',
                'tanggal_pensiun' => '2045-03-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Teknik Industri',
                'sekolah' => 'Universitas Gadjah Mada',
                'foto' => 'photos/default.png',
            ],
            [
                'nik' => '13004523',
                'nama' => 'KRISANTI NADYA KAMANANCY',
                'jabatan' => 'Asisten Personalia & Rekrutmen',
                'level' => 'Karpim',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'golongan' => 'IIID/00',
                'tanggal_dalam_jabatan' => '2023-01-01',
                'tmt_unit_kerja' => '2020-01-01',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '1988-06-25',
                'tmt_bekerja' => '2013-09-01',
                'tanggal_diangkat_staf' => '2015-01-01',
                'susunan_keluarga' => 'L',
                'job_grade' => 12,
                'person_grade' => 12,
                'tanggal_mbt' => '2043-06-01',
                'tanggal_pensiun' => '2044-07-01',
                'agama' => 'Kristen',
                'pendidikan_terakhir' => 'S1 Psikologi',
                'sekolah' => 'Universitas Padjadjaran',
                'foto' => 'photos/default.png',
            ],

            // --- KANTOR DIREKSI: KEUANGAN & AKUNTANSI ---
            [
                'nik' => '13004837',
                'nama' => 'HERRY WAHYUDI',
                'jabatan' => 'Kepala Bagian Keuangan & Akuntansi',
                'level' => 'Karpim',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN KEUANGAN & AKUNTANSI',
                'golongan' => 'IVA/06',
                'tanggal_dalam_jabatan' => '2022-09-01',
                'tmt_unit_kerja' => '2019-04-01',
                'tempat_lahir' => 'Tanjung Enim',
                'tanggal_lahir' => '1983-04-22',
                'tmt_bekerja' => '2007-06-01',
                'tanggal_diangkat_staf' => '2010-06-01',
                'susunan_keluarga' => 'K/2',
                'job_grade' => 16,
                'person_grade' => 15,
                'tanggal_mbt' => '2038-11-01',
                'tanggal_pensiun' => '2052-03-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Akuntansi',
                'sekolah' => 'Universitas Sriwijaya',
                'foto' => 'photos/default.png',
            ],
            [
                'nik' => '13002797',
                'nama' => 'TENGKU DIDI ZULKARNAEN',
                'jabatan' => 'Kepala Sub Bagian Anggaran & Perbendaharaan',
                'level' => 'Karpim',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN KEUANGAN & AKUNTANSI',
                'golongan' => 'IVB/06',
                'tanggal_dalam_jabatan' => '2023-01-01',
                'tmt_unit_kerja' => '2018-09-01',
                'tempat_lahir' => 'Medan',
                'tanggal_lahir' => '1974-03-11',
                'tmt_bekerja' => '1999-09-01',
                'tanggal_diangkat_staf' => '2004-09-01',
                'susunan_keluarga' => 'K/1',
                'job_grade' => 14,
                'person_grade' => 14,
                'tanggal_mbt' => '2029-10-01',
                'tanggal_pensiun' => '2041-05-01',
                'agama' => 'Katolik',
                'pendidikan_terakhir' => 'S1 Ekonomi',
                'sekolah' => 'Univ. Atma Jaya Yogyakarta',
                'foto' => 'photos/default.png',
            ],
            [
                'nik' => '13004521',
                'nama' => 'TRI INDAH SARY',
                'jabatan' => 'Asisten Akuntansi & Pajak',
                'level' => 'Karpim',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN KEUANGAN & AKUNTANSI',
                'golongan' => 'IIID/00',
                'tanggal_dalam_jabatan' => '2023-07-01',
                'tmt_unit_kerja' => '2020-01-01',
                'tempat_lahir' => 'Pontianak',
                'tanggal_lahir' => '1984-07-10',
                'tmt_bekerja' => '2010-01-01',
                'tanggal_diangkat_staf' => '2012-01-01',
                'susunan_keluarga' => 'L',
                'job_grade' => 11,
                'person_grade' => 12,
                'tanggal_mbt' => '2040-02-01',
                'tanggal_pensiun' => '2042-10-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Ekonomi',
                'sekolah' => 'Univ. Tanjungpura Pontianak',
                'foto' => 'photos/default.png',
            ],

            // --- DISTRIK RIAU: KEBUN LUBUK DALAM (Operasional Lapangan) ---
            [
                'nik' => '13005112',
                'nama' => 'BAMBANG HERMANTO',
                'jabatan' => 'Manajer Kebun',
                'level' => 'Karpim',
                'unit_kerja' => 'DISTRIK RIAU - KEBUN LUBUK DALAM',
                'golongan' => 'IVA/04',
                'tanggal_dalam_jabatan' => '2022-04-01',
                'tmt_unit_kerja' => '2022-04-01',
                'tempat_lahir' => 'Palembang',
                'tanggal_lahir' => '1978-01-20',
                'tmt_bekerja' => '2003-05-01',
                'tanggal_diangkat_staf' => '2007-01-01',
                'susunan_keluarga' => 'K/3',
                'job_grade' => 15,
                'person_grade' => 15,
                'tanggal_mbt' => '2033-01-01',
                'tanggal_pensiun' => '2034-02-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Pertanian',
                'sekolah' => 'Institut Pertanian Bogor',
                'foto' => 'photos/default.png',
            ],
            [
                'nik' => '13005220',
                'nama' => 'ILHAM PRASETYO',
                'jabatan' => 'Asisten Kepala (Askep) Rayon A',
                'level' => 'Karpim',
                'unit_kerja' => 'DISTRIK RIAU - KEBUN LUBUK DALAM',
                'golongan' => 'IIID/03',
                'tanggal_dalam_jabatan' => '2023-02-01',
                'tmt_unit_kerja' => '2021-08-01',
                'tempat_lahir' => 'Pekanbaru',
                'tanggal_lahir' => '1984-09-15',
                'tmt_bekerja' => '2009-03-01',
                'tanggal_diangkat_staf' => '2012-01-01',
                'susunan_keluarga' => 'K/2',
                'job_grade' => 13,
                'person_grade' => 13,
                'tanggal_mbt' => '2039-09-01',
                'tanggal_pensiun' => '2040-10-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Agronomi',
                'sekolah' => 'Universitas Riau',
                'foto' => 'photos/default.png',
            ],
            [
                'nik' => '13005331',
                'nama' => 'RIAN KURNIAWAN',
                'jabatan' => 'Asisten Tanaman Afdeling I',
                'level' => 'Karpim',
                'unit_kerja' => 'DISTRIK RIAU - KEBUN LUBUK DALAM',
                'golongan' => 'IIIB/04',
                'tanggal_dalam_jabatan' => '2023-08-01',
                'tmt_unit_kerja' => '2023-08-01',
                'tempat_lahir' => 'Bukittinggi',
                'tanggal_lahir' => '1990-12-05',
                'tmt_bekerja' => '2015-11-01',
                'tanggal_diangkat_staf' => '2018-01-01',
                'susunan_keluarga' => 'K/1',
                'job_grade' => 11,
                'person_grade' => 11,
                'tanggal_mbt' => '2045-12-01',
                'tanggal_pensiun' => '2046-01-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Budidaya Pertanian',
                'sekolah' => 'Universitas Andalas',
                'foto' => 'photos/default.png',
            ],

            // --- DISTRIK JAMBI - KEBUN RIMBO DUA (Operasional Lapangan) ---
            [
                'nik' => '13005445',
                'nama' => 'DEDI SUGIARTO',
                'jabatan' => 'Asisten Tanaman Afdeling II',
                'level' => 'Karpim',
                'unit_kerja' => 'DISTRIK JAMBI - KEBUN RIMBO DUA',
                'golongan' => 'IIIB/06',
                'tanggal_dalam_jabatan' => '2023-05-01',
                'tmt_unit_kerja' => '2022-01-01',
                'tempat_lahir' => 'Jambi',
                'tanggal_lahir' => '1989-07-22',
                'tmt_bekerja' => '2014-02-01',
                'tanggal_diangkat_staf' => '2016-01-01',
                'susunan_keluarga' => 'K/2',
                'job_grade' => 11,
                'person_grade' => 12,
                'tanggal_mbt' => '2044-07-01',
                'tanggal_pensiun' => '2045-08-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Pertanian',
                'sekolah' => 'Universitas Jambi',
                'foto' => 'photos/default.png',
            ],
            [
                'nik' => '13005501',
                'nama' => 'TRI ARIANY',
                'jabatan' => 'Kepala Tata Usaha (KTU)',
                'level' => 'Karpim',
                'unit_kerja' => 'DISTRIK JAMBI - KEBUN RIMBO DUA',
                'golongan' => 'IIIC/04',
                'tanggal_dalam_jabatan' => '2024-03-01',
                'tmt_unit_kerja' => '2024-03-01',
                'tempat_lahir' => 'Medan',
                'tanggal_lahir' => '1986-03-19',
                'tmt_bekerja' => '2012-05-01',
                'tanggal_diangkat_staf' => '2014-01-01',
                'susunan_keluarga' => 'K/1',
                'job_grade' => 12,
                'person_grade' => 13,
                'tanggal_mbt' => '2041-03-01',
                'tanggal_pensiun' => '2042-04-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Ekonomi Manajemen',
                'sekolah' => 'Universitas Sumatera Utara',
                'foto' => 'photos/default.png',
            ],

            // --- DISTRIK RIAU - PKS SEI PAGAR (Pabrik Kelapa Sawit) ---
            [
                'nik' => '13005610',
                'nama' => 'GUNAWAN WIBISONO',
                'jabatan' => 'Manajer Pabrik (Masinis Kepala)',
                'level' => 'Karpim',
                'unit_kerja' => 'DISTRIK RIAU - PKS SEI PAGAR',
                'golongan' => 'IVA/02',
                'tanggal_dalam_jabatan' => '2023-01-01',
                'tmt_unit_kerja' => '2023-01-01',
                'tempat_lahir' => 'Semarang',
                'tanggal_lahir' => '1981-10-10',
                'tmt_bekerja' => '2006-08-01',
                'tanggal_diangkat_staf' => '2009-01-01',
                'susunan_keluarga' => 'K/3',
                'job_grade' => 15,
                'person_grade' => 15,
                'tanggal_mbt' => '2036-10-01',
                'tanggal_pensiun' => '2037-11-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Teknik Mesin',
                'sekolah' => 'Universitas Diponegoro',
                'foto' => 'photos/default.png',
            ],
            [
                'nik' => '13005715',
                'nama' => 'FARHAN MAULANA',
                'jabatan' => 'Asisten Pengolahan Pabrik',
                'level' => 'Karpim',
                'unit_kerja' => 'DISTRIK RIAU - PKS SEI PAGAR',
                'golongan' => 'IIIB/02',
                'tanggal_dalam_jabatan' => '2023-11-01',
                'tmt_unit_kerja' => '2022-06-01',
                'tempat_lahir' => 'Pekanbaru',
                'tanggal_lahir' => '1991-04-18',
                'tmt_bekerja' => '2016-09-01',
                'tanggal_diangkat_staf' => '2019-01-01',
                'susunan_keluarga' => 'L',
                'job_grade' => 11,
                'person_grade' => 11,
                'tanggal_mbt' => '2046-04-01',
                'tanggal_pensiun' => '2047-05-01',
                'agama' => 'Islam',
                'pendidikan_terakhir' => 'S1 Teknik Kimia',
                'sekolah' => 'Universitas Riau',
                'foto' => 'photos/default.png',
            ],
        ];

        $this->command?->info('Seeding realistic employees...');
        foreach ($employeesData as $data) {
            Employee::updateOrCreate(['nik' => $data['nik']], $data);
        }

        // 2. Clear old job histories and rebuild a realistic, audited career timeline
        $this->command?->info('Rebuilding realistic JobHistory timelines and mutations...');
        JobHistory::query()->delete();

        $now = Carbon::now();

        $jobHistories = [
            // =========================================================================
            // A. HISTORICAL MUTATIONS (Past records showing promotions, rotations, transfers)
            // =========================================================================

            // DONNY USMAN: Started as Kasubag -> Promoted to Pj. Kabag SDM
            [
                'employee_nik' => '13004628',
                'jabatan' => 'Kepala Sub Bagian Perencanaan & Formasi SDM',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'level' => 'Karpim',
                'golongan' => 'IIID/06',
                'tmt_awal' => '2020-01-01',
                'tmt_akhir' => '2023-02-28',
                'jenis_mutasi' => 'ROTASI',
                'nomor_sk' => 'SK.DIR/REG5/ROT-SDM/014/XII/2019',
                'tanggal_sk' => '2019-12-20',
                'catatan' => 'Penetapan jabatan Kasubag Perencanaan SDM Kantor Direksi',
            ],
            [
                'employee_nik' => '13004628',
                'jabatan' => 'Pj. Kepala Bagian',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'level' => 'Karpim',
                'golongan' => 'IVA/05',
                'tmt_awal' => '2023-03-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'PROMOSI',
                'nomor_sk' => 'SK.DIR/REG5/PROM-SDM/003/II/2023',
                'tanggal_sk' => '2023-02-25',
                'catatan' => 'Promosi jabatan menjadi Pj. Kepala Bagian SDM & Sistem Manajemen',
            ],

            // WINNA FRIEDIANY: Was Kasubag HI -> Lateral Rotation to Kasubag Pengembangan SDM
            [
                'employee_nik' => '13002807',
                'jabatan' => 'Kepala Sub Bagian Kesejahteraan & HI',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'level' => 'Karpim',
                'golongan' => 'IVA/00',
                'tmt_awal' => '2021-05-01',
                'tmt_akhir' => '2024-01-14',
                'jenis_mutasi' => 'ROTASI',
                'nomor_sk' => 'SK.DIR/REG5/ROT-SDM/022/IV/2021',
                'tanggal_sk' => '2021-04-28',
                'catatan' => 'Rotasi internal Bagian SDM',
            ],
            [
                'employee_nik' => '13002807',
                'jabatan' => 'Kepala Sub Bagian Pengembangan SDM & Talenta',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'level' => 'Karpim',
                'golongan' => 'IVB/00',
                'tmt_awal' => '2024-01-15',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'ROTASI',
                'nomor_sk' => 'SK.DIR/REG5/ROT-SDM/005/I/2024',
                'tanggal_sk' => '2024-01-10',
                'catatan' => 'Rotasi memimpin Sub Bagian Pengembangan SDM & Talenta',
            ],

            // DJOKO PURWANTO: Active Kasubag Kesejahteraan & HI
            [
                'employee_nik' => '13004853',
                'jabatan' => 'Asisten Hubungan Industrial',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'level' => 'Karpim',
                'golongan' => 'IIIC/02',
                'tmt_awal' => '2019-08-01',
                'tmt_akhir' => '2024-01-31',
                'jenis_mutasi' => 'ROTASI',
                'nomor_sk' => 'SK.DIR/REG5/ROT-SDM/033/VII/2019',
                'tanggal_sk' => '2019-07-25',
                'catatan' => 'Penugasan Asisten Hubungan Industrial',
            ],
            [
                'employee_nik' => '13004853',
                'jabatan' => 'Kepala Sub Bagian Kesejahteraan & Hubungan Industrial',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'level' => 'Karpim',
                'golongan' => 'IIID/05',
                'tmt_awal' => '2024-02-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'PROMOSI',
                'nomor_sk' => 'SK.DIR/REG5/PROM-SDM/008/I/2024',
                'tanggal_sk' => '2024-01-28',
                'catatan' => 'Promosi dari Asisten menjadi Kepala Sub Bagian',
            ],

            // ADHITYA YUDA ANGGARA: Active Asisten SDM
            [
                'employee_nik' => '13004867',
                'jabatan' => 'Asisten Manajemen Kinerja SDM',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'level' => 'Karpim',
                'golongan' => 'IIIC/02',
                'tmt_awal' => '2023-06-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'PENUGASAN',
                'nomor_sk' => 'SK.DIR/REG5/TUGAS-SDM/041/V/2023',
                'tanggal_sk' => '2023-05-28',
                'catatan' => 'Penetapan Asisten Manajemen Kinerja SDM',
            ],

            // KRISANTI NADYA KAMANANCY: Active Asisten Personalia
            [
                'employee_nik' => '13004523',
                'jabatan' => 'Asisten Personalia & Rekrutmen',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'level' => 'Karpim',
                'golongan' => 'IIID/00',
                'tmt_awal' => '2023-01-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'ROTASI',
                'nomor_sk' => 'SK.DIR/REG5/ROT-SDM/002/I/2023',
                'tanggal_sk' => '2022-12-28',
                'catatan' => 'Penugasan Asisten Personalia & Rekrutmen',
            ],

            // HERRY WAHYUDI: Kabag Keuangan & Akuntansi
            [
                'employee_nik' => '13004837',
                'jabatan' => 'Kepala Bagian Keuangan & Akuntansi',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN KEUANGAN & AKUNTANSI',
                'level' => 'Karpim',
                'golongan' => 'IVA/06',
                'tmt_awal' => '2022-09-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'PROMOSI',
                'nomor_sk' => 'SK.DIR/REG5/PROM-KEU/011/VIII/2022',
                'tanggal_sk' => '2022-08-20',
                'catatan' => 'Promosi Kepala Bagian Keuangan & Akuntansi',
            ],

            // TENGKU DIDI ZULKARNAEN: Kasubag Anggaran
            [
                'employee_nik' => '13002797',
                'jabatan' => 'Kepala Sub Bagian Anggaran & Perbendaharaan',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN KEUANGAN & AKUNTANSI',
                'level' => 'Karpim',
                'golongan' => 'IVB/06',
                'tmt_awal' => '2023-01-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'ROTASI',
                'nomor_sk' => 'SK.DIR/REG5/ROT-KEU/003/XII/2022',
                'tanggal_sk' => '2022-12-15',
                'catatan' => 'Rotasi jabatan Kasubag Anggaran & Perbendaharaan',
            ],

            // TRI INDAH SARY: Asisten Akuntansi
            [
                'employee_nik' => '13004521',
                'jabatan' => 'Asisten Akuntansi & Pajak',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN KEUANGAN & AKUNTANSI',
                'level' => 'Karpim',
                'golongan' => 'IIID/00',
                'tmt_awal' => '2023-07-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'PENUGASAN',
                'nomor_sk' => 'SK.DIR/REG5/TUGAS-KEU/021/VI/2023',
                'tanggal_sk' => '2023-06-25',
                'catatan' => 'Penugasan Asisten Akuntansi & Pajak',
            ],

            // BAMBANG HERMANTO: Manajer Kebun Lubuk Dalam
            [
                'employee_nik' => '13005112',
                'jabatan' => 'Manajer Kebun',
                'unit_kerja' => 'DISTRIK RIAU - KEBUN LUBUK DALAM',
                'level' => 'Karpim',
                'golongan' => 'IVA/04',
                'tmt_awal' => '2022-04-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'MUTASI_UNIT',
                'nomor_sk' => 'SK.DIR/REG5/MUT-OPS/019/III/2022',
                'tanggal_sk' => '2022-03-20',
                'catatan' => 'Penempatan Manajer Kebun Lubuk Dalam',
            ],

            // ILHAM PRASETYO: Askep Rayon A Kebun Lubuk Dalam
            [
                'employee_nik' => '13005220',
                'jabatan' => 'Asisten Kepala (Askep) Rayon A',
                'unit_kerja' => 'DISTRIK RIAU - KEBUN LUBUK DALAM',
                'level' => 'Karpim',
                'golongan' => 'IIID/03',
                'tmt_awal' => '2023-02-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'PROMOSI',
                'nomor_sk' => 'SK.DIR/REG5/PROM-OPS/006/I/2023',
                'tanggal_sk' => '2023-01-25',
                'catatan' => 'Promosi dari Asisten Tanaman menjadi Askep Rayon',
            ],

            // RIAN KURNIAWAN: Asisten Tanaman Afdeling I (Kebun Lubuk Dalam)
            [
                'employee_nik' => '13005331',
                'jabatan' => 'Asisten Tanaman Afdeling I',
                'unit_kerja' => 'DISTRIK RIAU - KEBUN LUBUK DALAM',
                'level' => 'Karpim',
                'golongan' => 'IIIB/04',
                'tmt_awal' => '2023-08-01',
                'tmt_akhir' => null, // CURRENT ACTIVE - READY FOR SWAP WITH DEDI SUGIARTO!
                'jenis_mutasi' => 'PENUGASAN',
                'nomor_sk' => 'SK.DIR/REG5/TUGAS-OPS/052/VII/2023',
                'tanggal_sk' => '2023-07-20',
                'catatan' => 'Penempatan Asisten Tanaman Afdeling I Kebun Lubuk Dalam',
            ],

            // DEDI SUGIARTO: Asisten Tanaman Afdeling II (Kebun Rimbo Dua)
            [
                'employee_nik' => '13005445',
                'jabatan' => 'Asisten Tanaman Afdeling II',
                'unit_kerja' => 'DISTRIK JAMBI - KEBUN RIMBO DUA',
                'level' => 'Karpim',
                'golongan' => 'IIIB/06',
                'tmt_awal' => '2023-05-01',
                'tmt_akhir' => null, // CURRENT ACTIVE - READY FOR SWAP WITH RIAN KURNIAWAN!
                'jenis_mutasi' => 'MUTASI_UNIT',
                'nomor_sk' => 'SK.DIR/REG5/MUT-OPS/031/IV/2023',
                'tanggal_sk' => '2023-04-18',
                'catatan' => 'Mutasi antar unit ke Kebun Rimbo Dua',
            ],

            // TRI ARIANY: KTU Kebun Rimbo Dua (Moved from SDM HQ previously)
            [
                'employee_nik' => '13005501',
                'jabatan' => 'Asisten Hubungan Industrial',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN SDM & SISTEM MANAJEMEN',
                'level' => 'Karpim',
                'golongan' => 'IIIB/04',
                'tmt_awal' => '2021-08-01',
                'tmt_akhir' => '2024-02-28',
                'jenis_mutasi' => 'ROTASI',
                'nomor_sk' => 'SK.DIR/REG5/ROT-SDM/041/VII/2021',
                'tanggal_sk' => '2021-07-25',
                'catatan' => 'Tugas staf SDM Kantor Direksi',
            ],
            [
                'employee_nik' => '13005501',
                'jabatan' => 'Kepala Tata Usaha (KTU)',
                'unit_kerja' => 'DISTRIK JAMBI - KEBUN RIMBO DUA',
                'level' => 'Karpim',
                'golongan' => 'IIIC/04',
                'tmt_awal' => '2024-03-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'MUTASI_UNIT',
                'nomor_sk' => 'SK.DIR/REG5/MUT-OPS/012/II/2024',
                'tanggal_sk' => '2024-02-20',
                'catatan' => 'Mutasi penempatan sebagai Kepala Tata Usaha (KTU) Unit Rimbo Dua',
            ],

            // GUNAWAN WIBISONO: Manajer PKS Sei Pagar
            [
                'employee_nik' => '13005610',
                'jabatan' => 'Manajer Pabrik (Masinis Kepala)',
                'unit_kerja' => 'DISTRIK RIAU - PKS SEI PAGAR',
                'level' => 'Karpim',
                'golongan' => 'IVA/02',
                'tmt_awal' => '2023-01-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'PROMOSI',
                'nomor_sk' => 'SK.DIR/REG5/PROM-PKS/001/XII/2022',
                'tanggal_sk' => '2022-12-18',
                'catatan' => 'Promosi memimpin Unit Pabrik Kelapa Sawit Sei Pagar',
            ],

            // FARHAN MAULANA: Asisten Pengolahan PKS Sei Pagar
            [
                'employee_nik' => '13005715',
                'jabatan' => 'Asisten Pengolahan Pabrik',
                'unit_kerja' => 'DISTRIK RIAU - PKS SEI PAGAR',
                'level' => 'Karpim',
                'golongan' => 'IIIB/02',
                'tmt_awal' => '2023-11-01',
                'tmt_akhir' => null, // CURRENT ACTIVE
                'jenis_mutasi' => 'PENUGASAN',
                'nomor_sk' => 'SK.DIR/REG5/TUGAS-PKS/038/X/2023',
                'tanggal_sk' => '2023-10-25',
                'catatan' => 'Penugasan Asisten Pengolahan Shift A',
            ],

            // =========================================================================
            // B. REALISTIC VACANCIES (JABATAN KOSONG)
            // Closed job_history where NO current employee holds this exact (jabatan, unit_kerja)
            // =========================================================================

            // VACANCY 1: Green Severity (< 30 days) - 15 days vacant
            // Former occupant: TRI INDAH SARY was promoted/rotated out to Pajak
            [
                'employee_nik' => '13004521',
                'jabatan' => 'Kepala Sub Bagian Akuntansi Biaya',
                'unit_kerja' => 'KANTOR DIREKSI - BAGIAN KEUANGAN & AKUNTANSI',
                'level' => 'Karpim',
                'golongan' => 'IIID/02',
                'tmt_awal' => '2023-01-01',
                'tmt_akhir' => Carbon::now()->subDays(15)->format('Y-m-d'), // VACANT FOR 15 DAYS
                'jenis_mutasi' => 'ROTASI',
                'nomor_sk' => 'SK.DIR/REG5/ROT-KEU/019/VIII/2026',
                'tanggal_sk' => Carbon::now()->subDays(20)->format('Y-m-d'),
                'catatan' => 'Pejabat dipindahkan tugas; posisi Sub Bagian Akuntansi Biaya sementara lowong',
            ],

            // VACANCY 2: Yellow Severity (30 - 90 days) - 48 days vacant
            // Former occupant: DEDI SUGIARTO was transferred from Afdeling III to Afdeling II
            [
                'employee_nik' => '13005445',
                'jabatan' => 'Asisten Tanaman Afdeling III',
                'unit_kerja' => 'DISTRIK JAMBI - KEBUN RIMBO DUA',
                'level' => 'Karpim',
                'golongan' => 'IIIB/04',
                'tmt_awal' => '2022-01-01',
                'tmt_akhir' => Carbon::now()->subDays(48)->format('Y-m-d'), // VACANT FOR 48 DAYS
                'jenis_mutasi' => 'MUTASI_UNIT',
                'nomor_sk' => 'SK.DIR/REG5/MUT-OPS/045/VII/2026',
                'tanggal_sk' => Carbon::now()->subDays(55)->format('Y-m-d'),
                'catatan' => 'Posisi Asisten Tanaman Afdeling III kosong menunggu formasi baru',
            ],

            // VACANCY 3: Red Severity (> 90 days - Critical) - 110 days vacant
            // Former occupant: TRI ARIANY left PKS to become KTU at Kebun Rimbo Dua
            [
                'employee_nik' => '13005501',
                'jabatan' => 'Asisten Tata Usaha (KTU) PKS',
                'unit_kerja' => 'DISTRIK RIAU - PKS SEI PAGAR',
                'level' => 'Karpim',
                'golongan' => 'IIIC/02',
                'tmt_awal' => '2022-06-01',
                'tmt_akhir' => Carbon::now()->subDays(110)->format('Y-m-d'), // CRITICAL: 110 DAYS VACANT
                'jenis_mutasi' => 'MUTASI_UNIT',
                'nomor_sk' => 'SK.DIR/REG5/MUT-PKS/021/V/2026',
                'tanggal_sk' => Carbon::now()->subDays(115)->format('Y-m-d'),
                'catatan' => 'Posisi KTU Pabrik kosong sejak mutasi antar unit, prioritas pengisian penempatan baru',
            ],
        ];

        foreach ($jobHistories as $jh) {
            JobHistory::create($jh);
        }

        $this->command?->info('Realistic Mutation dummy data seeded successfully!');
    }
}
