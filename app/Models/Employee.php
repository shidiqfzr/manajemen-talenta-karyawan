<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $primaryKey = 'nik';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Master 43 Unit Kerja resmi PTPN IV Regional V (Sesuai DOMAIN.md Bab 3).
     */
    public const OFFICIAL_UNITS = [
        'Bagian Sekretariat dan Hukum',
        'Bagian Sumber Daya Manusia dan Sistem Manajemen',
        'Bagian Tanaman',
        'Bagian Teknik dan Pengolahan',
        'Bagian Akuntansi dan Keuangan',
        'Tim Penguatan ERP SAP',
        'Bagian Pengadaan dan Teknologi Informasi',
        'Divisi Sistem Manajemen & Sustainability',
        'Project Management Office (PMO)',
        'Satuan Pengawasan Internal (SPI)',
        'Distrik Petani Mitra',
        'Kebun Kemitraan Area Kalbar',
        'Kebun Kemitraan Area Kaltim-Sel',
        'Unit Group Kalimantan Barat',
        'Kebun Inti Gunung Meliau',
        'PKS Gunung Meliau',
        'Kebun Gunung Emas',
        'Kebun Inti Sungai Dekan',
        'Kebun Rimba Belian',
        'PKS Rimba Belian',
        'Kebun PKR Sintang',
        'Kebun Ngabang',
        'PKS Ngabang',
        'Kebun Parindu',
        'PKS Parindu',
        'Kebun Kembayan',
        'PKS Kembayan',
        'Unit Group Kalimantan Selatan/Tengah',
        'Kebun Danau Salak',
        'Kebun Pamukan',
        'PKS Pamukan',
        'Kebun Batulicin',
        'Kebun Pelaihari',
        'PKS Pelaihari',
        'Proyek Batubara Danau Salak & Buntok',
        'Kebun Raren Batuah',
        'Unit Group Wilayah Kalimantan Timur',
        'Kebun Tabara',
        'Kebun Tajati',
        'Kebun Inti Pandawa',
        'PKS Longpinang',
        'PKS Samuntai',
        'Kebun-PKS Longkali',
    ];

    /**
     * Master 43 Unit Kerja resmi PTPN IV Regional V dikelompokkan per Wilayah (DOMAIN.md Bab 3).
     */
    public const GROUPED_UNITS = [
        'Regional Office (Kantor Direksi Pontianak)' => [
            'Bagian Sekretariat dan Hukum',
            'Bagian Sumber Daya Manusia dan Sistem Manajemen',
            'Bagian Tanaman',
            'Bagian Teknik dan Pengolahan',
            'Bagian Akuntansi dan Keuangan',
            'Tim Penguatan ERP SAP',
            'Bagian Pengadaan dan Teknologi Informasi',
            'Divisi Sistem Manajemen & Sustainability',
            'Project Management Office (PMO)',
            'Satuan Pengawasan Internal (SPI)',
            'Distrik Petani Mitra',
            'Kebun Kemitraan Area Kalbar',
            'Kebun Kemitraan Area Kaltim-Sel',
        ],
        'Wilayah Kalimantan Barat' => [
            'Unit Group Kalimantan Barat',
            'Kebun Inti Gunung Meliau',
            'PKS Gunung Meliau',
            'Kebun Gunung Emas',
            'Kebun Inti Sungai Dekan',
            'Kebun Rimba Belian',
            'PKS Rimba Belian',
            'Kebun PKR Sintang',
            'Kebun Ngabang',
            'PKS Ngabang',
            'Kebun Parindu',
            'PKS Parindu',
            'Kebun Kembayan',
            'PKS Kembayan',
        ],
        'Wilayah Kalimantan Selatan & Tengah' => [
            'Unit Group Kalimantan Selatan/Tengah',
            'Kebun Danau Salak',
            'Kebun Pamukan',
            'PKS Pamukan',
            'Kebun Batulicin',
            'Kebun Pelaihari',
            'PKS Pelaihari',
            'Proyek Batubara Danau Salak & Buntok',
            'Kebun Raren Batuah',
        ],
        'Wilayah Kalimantan Timur' => [
            'Unit Group Wilayah Kalimantan Timur',
            'Kebun Tabara',
            'Kebun Tajati',
            'Kebun Inti Pandawa',
            'PKS Longpinang',
            'PKS Samuntai',
            'Kebun-PKS Longkali',
        ],
    ];

    /**
     * 6 Agama Resmi di Indonesia (Standar BKN / BUMN).
     */
    public const RELIGIONS = [
        'Islam',
        'Kristen Protestan',
        'Katolik',
        'Hindu',
        'Buddha',
        'Khonghucu',
    ];

    /**
     * Pilihan Jenis Kelamin.
     */
    public const GENDERS = [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ];

    /**
     * Strata Pegawai (DOMAIN.md Bab 3).
     */
    public const LEVELS = [
        'Karpim' => 'Karyawan Pimpinan (Karpim)',
        'Karpel' => 'Karyawan Pelaksana (Karpel)',
    ];

    /**
     * Eselon Manajemen / RM Band Formasi (DOMAIN.md Bab 4.2).
     */
    public const RM_LEVELS = [
        'RM-1' => 'RM-1 — Pimpinan Puncak Unit / Senior Leadership',
        'RM-2' => 'RM-2 — Manajemen Madya / Middle Management',
        'RM-3' => 'RM-3 — Manajemen Pratama / First-Line Management',
        'RM-4' => 'RM-4 — Karyawan Pelaksana / Operational Staff',
    ];

    /**
     * Susunan Keluarga / Status PTKP Pajak & Tunjangan BUMN.
     */
    public const FAMILY_STATUSES = [
        'L' => 'L (Lajang)',
        'TK' => 'TK (Tidak Kawin)',
        'K/0' => 'K/0 (Kawin, 0 Tanggungan)',
        'K/1' => 'K/1 (Kawin, 1 Anak)',
        'K/2' => 'K/2 (Kawin, 2 Anak)',
        'K/3' => 'K/3 (Kawin, 3 Anak)',
    ];

    /**
     * Jenjang Pendidikan Terakhir Standar.
     */
    public const EDUCATION_LEVELS = [
        'SD',
        'SMP',
        'SMA / SMK',
        'D1 / D2',
        'D3',
        'D4 / S1',
        'S2',
        'S3',
    ];

    /**
     * Jalur Rekrutmen / Pengadaan Pegawai (DOMAIN.md Bab 2.1).
     */
    public const ENTRY_CHANNELS = [
        'CKP' => 'CKP (Calon Karyawan Pimpinan / Talenta Internal)',
        'Talent Scouting' => 'Talent Scouting (Kampus Unggulan)',
        'RBB' => 'RBB (Rekrutmen Bersama BUMN)',
        'Reguler' => 'Reguler (Pengadaan Umum / Eksternal)',
    ];

    /**
     * Hitung Batas Usia Pensiun (56 tahun, awal bulan kelahiran).
     */
    public static function calculateTanggalPensiun(?string $tanggalLahir): ?string
    {
        if (empty($tanggalLahir)) {
            return null;
        }

        try {
            return Carbon::parse($tanggalLahir)->addYears(56)->startOfMonth()->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Hitung Masa Bebas Tugas (MBT: T-1 tahun sebelum pensiun / 55 tahun).
     */
    public static function calculateTanggalMbt(?string $tanggalPensiun): ?string
    {
        if (empty($tanggalPensiun)) {
            return null;
        }

        try {
            return Carbon::parse($tanggalPensiun)->subYear()->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    protected $fillable = [
        'nik',
        'nama',
        'jabatan',
        'level',
        'rm_level',
        'unit_kerja',
        'golongan',
        'tanggal_dalam_jabatan',
        'tmt_unit_kerja',
        'tempat_lahir',
        'jenis_kelamin',
        'tanggal_lahir',
        'tmt_bekerja',
        'tanggal_diangkat_staf',
        'susunan_keluarga',
        'job_grade',
        'person_grade',
        'jalur_masuk',
        'tanggal_mbt',
        'tanggal_pensiun',
        'agama',
        'pendidikan_terakhir',
        'sekolah',
        'foto',
    ];

    protected $casts = [
        'tanggal_dalam_jabatan' => 'date',
        'tmt_unit_kerja' => 'date',
        'tanggal_lahir' => 'date',
        'tmt_bekerja' => 'date',
        'tanggal_diangkat_staf' => 'date',
        'tanggal_mbt' => 'date',
        'tanggal_pensiun' => 'date',
    ];

    public function trainings()
    {
        return $this->belongsToMany(Training::class, 'employee_training', 'employee_nik', 'training_id')
            ->withPivot(['sertifikat'])
            ->withTimestamps();
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'employee_nik', 'nik');
    }

    public function jobHistories()
    {
        return $this->hasMany(JobHistory::class, 'employee_nik', 'nik')
            ->orderByDesc('tmt_awal');
    }

    public function currentJob()
    {
        return $this->hasOne(JobHistory::class, 'employee_nik', 'nik')
            ->whereNull('tmt_akhir')
            ->latestOfMany('tmt_awal');
    }
}
