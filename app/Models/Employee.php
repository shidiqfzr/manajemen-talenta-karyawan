<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use App\Services\ManPowerPlanningService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory, Searchable;

    /**
     * Columns that are searchable via scopeSearch().
     */
    protected array $searchable = [
        'nik',
        'nama',
        'jabatan',
        'unit_kerja',
    ];

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
     * Status Kepegawaian.
     */
    public const EMPLOYMENT_STATUSES = [
        'Tetap (PKWTT)' => 'Karyawan Tetap (PKWTT)',
        'Kontrak (PKWT)' => 'Karyawan Kontrak (PKWT)',
        'Calon Pegawai' => 'Calon Pegawai / Masa Percobaan',
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
        'status_penugasan',
        'status_karyawan',
        'tanggal_berakhir_kontrak',
        'tanggal_berakhir_sanksi',
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
        'tanggal_berakhir_sanksi' => 'date',
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
            ->where('status_penugasan', 'Definitif')
            ->latestOfMany('tmt_awal');
    }

    public function activeJobs()
    {
        return $this->hasMany(JobHistory::class, 'employee_nik', 'nik')
            ->whereNull('tmt_akhir')
            ->orderByDesc('tmt_awal');
    }

    public function concurrentJobs()
    {
        return $this->hasMany(JobHistory::class, 'employee_nik', 'nik')
            ->whereNull('tmt_akhir')
            ->where('status_penugasan', '!=', 'Definitif')
            ->orderByDesc('tmt_awal');
    }

    public function hasConcurrentJobs(): bool
    {
        return $this->concurrentJobs()->exists();
    }

    /**
     * Cek apakah karyawan sedang menjalani masa sanksi indisipliner aktif.
     */
    public function isSanctionActive(): bool
    {
        return ! empty($this->tanggal_berakhir_sanksi) && $this->tanggal_berakhir_sanksi->gte(Carbon::today());
    }

    /**
     * Cek apakah masa sanksi karyawan telah kedaluwarsa dan perlu dicabut/dimutasi.
     */
    public function isSanctionExpired(): bool
    {
        return ! empty($this->tanggal_berakhir_sanksi) && $this->tanggal_berakhir_sanksi->lt(Carbon::today());
    }

    /**
     * Ambil tanggal pensiun efektif (menggunakan tanggal_pensiun atau dihitung dari tanggal_lahir + 56 thn).
     */
    public function getEffectiveTanggalPensiunAttribute(): ?Carbon
    {
        if ($this->tanggal_pensiun) {
            return Carbon::parse($this->tanggal_pensiun);
        }

        if ($this->tanggal_lahir) {
            return Carbon::parse($this->tanggal_lahir)->addYears(56)->startOfMonth();
        }

        return null;
    }

    /**
     * Ambil tanggal MBT efektif (T-1 tahun sebelum tanggal pensiun / usia 55 thn).
     */
    public function getEffectiveTanggalMbtAttribute(): ?Carbon
    {
        if ($this->tanggal_mbt) {
            return Carbon::parse($this->tanggal_mbt);
        }

        if ($pensiun = $this->effective_tanggal_pensiun) {
            return (clone $pensiun)->subYear();
        }

        return null;
    }

    /**
     * Cek apakah pegawai telah mencapai atau melewati Batas Usia Pensiun (BUP 56 thn).
     */
    public function isPensionReached(): bool
    {
        $pensiun = $this->effective_tanggal_pensiun;

        return $pensiun ? Carbon::today()->gte($pensiun) : false;
    }

    /**
     * Cek apakah pegawai sedang dalam Masa Bebas Tugas aktif (usia 55 s.d. 56 thn).
     */
    public function isMbtActive(): bool
    {
        $mbt = $this->effective_tanggal_mbt;
        $pensiun = $this->effective_tanggal_pensiun;

        if (! $mbt) {
            return false;
        }

        $today = Carbon::today();

        return $today->gte($mbt) && (! $pensiun || $today->lt($pensiun));
    }

    /**
     * Cek apakah pegawai menjelang MBT (sisa waktu <= 12 bulan menuju tanggal_mbt).
     */
    public function isApproachingMbt(): bool
    {
        $mbt = $this->effective_tanggal_mbt;

        if (! $mbt) {
            return false;
        }

        $today = Carbon::today();

        return $today->lt($mbt) && $today->diffInDays($mbt) <= 365;
    }

    /**
     * Accessor untuk teks hitung mundur sisa bulan menuju MBT.
     */
    public function getMbtCountdownAttribute(): ?string
    {
        $mbt = $this->effective_tanggal_mbt;

        if (! $mbt) {
            return null;
        }

        $today = Carbon::today();
        $diffDays = $today->diffInDays($mbt);
        $diffMonths = (int) ceil($diffDays / 30);

        if ($diffMonths <= 0) {
            return '< 1 bln';
        }

        return $diffMonths.' bln';
    }

    /**
     * Cek apakah jabatan definitif karyawan adalah formasi penampungan fungsional (Advisor/Overflow).
     */
    public function isOverflow(): bool
    {
        if (empty($this->jabatan)) {
            return false;
        }

        if (str_contains($this->jabatan, 'Advisor')) {
            return true;
        }

        $position = Position::getAllCached()->get($this->jabatan);

        return $position ? ($position->is_overflow && ! $position->is_diperban) : false;
    }

    /**
     * Scope query untuk pegawai dalam masa sanksi indisipliner aktif.
     */
    public function scopeSanctionActive($query)
    {
        return $query->whereNotNull('tanggal_berakhir_sanksi')
            ->where('tanggal_berakhir_sanksi', '>=', Carbon::today()->toDateString());
    }

    /**
     * Scope query untuk pegawai dengan masa sanksi kedaluwarsa.
     */
    public function scopeSanctionExpired($query)
    {
        return $query->whereNotNull('tanggal_berakhir_sanksi')
            ->where('tanggal_berakhir_sanksi', '<', Carbon::today()->toDateString());
    }

    /**
     * Scope query untuk semua pegawai yang memiliki riwayat/status sanksi.
     */
    public function scopeSanctionAll($query)
    {
        return $query->whereNotNull('tanggal_berakhir_sanksi');
    }

    /**
     * Scope query untuk pegawai pada posisi overflow fungsional (Advisor / Pool).
     */
    public function scopeOverflow($query)
    {
        $overflowNames = Position::getOverflowPositionNames();

        return $query->where(function ($q) use ($overflowNames) {
            if (! empty($overflowNames)) {
                $q->whereIn('jabatan', $overflowNames);
            }
            $q->orWhere('jabatan', 'like', '%Advisor%');
        });
    }

    /**
     * Scope query untuk Karyawan Pimpinan (Karpim).
     */
    public function scopeKarpim($query)
    {
        return $query->where(function ($q) {
            $q->where('level', 'like', '%Karpim%')
                ->orWhere('level', 'like', '%Pimpinan%');
        });
    }

    /**
     * Scope query untuk Karyawan Pelaksana (Karpel / Pelaksana).
     */
    public function scopePelaksana($query)
    {
        return $query->where(function ($q) {
            $q->where('level', 'like', '%Pelaksana%')
                ->orWhere('level', 'like', '%Karpel%');
        });
    }

    /**
     * Accessor untuk mengecek apakah pegawai merupakan Karpim.
     */
    public function getIsKarpimAttribute(): bool
    {
        $lvl = strtolower((string) ($this->level ?? ''));

        return str_contains($lvl, 'karpim') || str_contains($lvl, 'pimpinan');
    }

    /**
     * Accessor untuk mengambil 2 huruf inisial nama pegawai.
     */
    public function getInitialsAttribute(): string
    {
        $initials = collect(explode(' ', (string) $this->nama))
            ->filter()
            ->take(2)
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->implode('');

        return $initials ?: 'HR';
    }

    /**
     * Accessor untuk menentukan Bidang Fungsional via ManPowerPlanningService (DOMAIN.md Bab 4 & 9).
     */
    public function getBidangAttribute(): string
    {
        return app(ManPowerPlanningService::class)->determineBidang($this);
    }

    /**
     * Accessor untuk token CSS styling Bidang Fungsional (Neutral Subtle Tag untuk mencegah visual clutter).
     */
    public function getBidangThemeAttribute(): string
    {
        return 'bg-slate-100 text-slate-600 border-slate-200';
    }

    /**
     * Accessor untuk styling token CSS RM Band sesuai DOMAIN.md Bab 10 & DESIGN.md.
     */
    public function getRmThemeAttribute(): string
    {
        return match ($this->rm_level) {
            'RM-1' => 'bg-orange-50 text-orange-800 border-orange-200',
            'RM-2' => 'bg-sky-50 text-sky-800 border-sky-200',
            'RM-3' => 'bg-purple-50 text-purple-800 border-purple-200',
            'RM-4' => 'bg-slate-100 text-slate-700 border-slate-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }

    /**
     * Accessor untuk token CSS styling Strata Pegawai (Level) sesuai DOMAIN.md Bab 10.
     */
    public function getLevelThemeAttribute(): string
    {
        return $this->is_karpim
            ? 'bg-primary-50 text-primary-800 border-primary-200'
            : 'bg-slate-100 text-slate-700 border-slate-200';
    }
}
