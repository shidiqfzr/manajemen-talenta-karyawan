<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\MppSupplyPlan;
use App\Models\Position;
use App\Services\Formasi\FormasiCatalog;
use App\Services\Mpp\MppSupplyPlanService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ManPowerPlanningService
{
    /** Rentang tahun perencanaan MPP yang dapat dipilih dan direncanakan. */
    public const PLANNING_YEAR_MIN = 2024;

    public const PLANNING_YEAR_MAX = 2031;

    /** Nama bulan untuk label layar (tidak bergantung pada locale aplikasi). */
    public const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public const MONTHS_SHORT = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /** Jalur masuk karyawan (DOMAIN.md §2) yang merealisasikan sumber pasokan rencana. */
    private const SUPPLY_CHANNELS = [
        'CKP' => 'ckp',
        'Talent Scouting' => 'talent_scouting',
        'RBB' => 'rbb',
    ];

    /**
     * Label RM Band Karpim untuk layar MPP, diturunkan dari DOMAIN.md Bab 4.2. Satu sumber agar header
     * matriks dan legenda tidak menyimpang dari pedoman (mis. Manajer Kebun dan Kepala Bagian = RM-1).
     */
    public const RM_BANDS = [
        'RM-1' => [
            'short' => 'Pimpinan Puncak / Gol. IV',
            'positions' => 'Kepala Bagian, Manajer Kebun, Manajer PKS, General Manager, Koordinator SPI (Gol. IV-A–D)',
        ],
        'RM-2' => [
            'short' => 'Madya / Gol. III-C–D',
            'positions' => 'Kasubag, Askep Tanaman, Askep Pengolahan / Masinis Kepala (Gol. III-C–D)',
        ],
        'RM-3' => [
            'short' => 'Pratama / Gol. III-A–B',
            'positions' => 'Asisten Afdeling, Asisten Pengolahan, Asisten Bengkel, KTU, Asisten SDM (Gol. III-A–B)',
        ],
    ];

    /**
     * Hasil pembacaan karyawan per tanggal posisi, untuk satu instance (satu permintaan). Matriks, drilldown,
     * dan render ulang pada permintaan yang sama memakai satu pembacaan tabel karyawan.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $presentCache = [];

    public function __construct(
        private ?FormasiCatalog $catalog = null,
        private ?MppSupplyPlanService $supplyPlan = null,
    ) {}

    private function catalog(): FormasiCatalog
    {
        return $this->catalog ??= app(FormasiCatalog::class);
    }

    private function supplyPlan(): MppSupplyPlanService
    {
        return $this->supplyPlan ??= app(MppSupplyPlanService::class);
    }

    /**
     * Total Standar Formasi Karpim (RM-1 s.d. RM-3) dari kursi master formasi.
     */
    public function totalStandarFormasi(): int
    {
        return array_sum(array_map('array_sum', $this->catalog()->standarFormasi()));
    }

    /**
     * Pasokan (CKP + Talent Scouting + RBB) yang direncanakan untuk satu sel pada satu tahun.
     */
    public function supplyFor(int $year, string $rm, string $bidang): int
    {
        $plan = $this->supplyPlan()->matrixFor($year);

        return array_sum(array_map(fn (string $source): int => $plan[$source][$rm][$bidang] ?? 0, MppSupplyPlan::SUPPLY_SOURCES));
    }

    /**
     * Standar Formasi Karpim per RM Band dan bidang, diturunkan dari kursi master formasi.
     *
     * @return array<string, array<string, int>>
     */
    public function standarFormasi(): array
    {
        return $this->catalog()->standarFormasi();
    }

    /**
     * Bulan "Posisi per" bawaan: bulan berjalan untuk tahun berjalan, Desember untuk tahun lalu
     * (posisi akhir tahun), Januari untuk tahun mendatang (posisi awal tahun dari data saat ini).
     */
    public function defaultAsOfMonth(int $year): int
    {
        $now = Carbon::now();

        return match (true) {
            $year === $now->year => $now->month,
            $year < $now->year => 12,
            default => 1,
        };
    }

    public function monthLabel(int $month, int $year): string
    {
        return self::MONTHS_SHORT[$month].' '.$year;
    }

    /** Tanggal posisi: hari terakhir bulan posisi. */
    private function asOfDate(int $year, int $month): Carbon
    {
        return Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();
    }

    /**
     * Klasifikasi RM Band & Bidang untuk pratinjau form, lengkap dengan penanda apakah nilainya
     * berasal dari Master Formasi (penetapan) atau dari pendeteksian cadangan (perkiraan).
     *
     * @return array{bidang: string, rm_band: string, bidang_from_formasi: bool, rm_from_formasi: bool}
     */
    public function classifyPosition(Employee $emp): array
    {
        $formasiRm = filled($emp->jabatan) ? Position::getAllCached()->get((string) $emp->jabatan)?->rm_level : null;
        $formasiBidang = filled($emp->jabatan) && filled($emp->unit_kerja)
            ? $this->catalog()->seatBidang((string) $emp->unit_kerja, (string) $emp->jabatan)
            : null;

        return [
            'bidang' => $formasiBidang ?? $this->determineBidang($emp),
            'rm_band' => $formasiRm ?? $this->determineRmLevel($emp),
            'bidang_from_formasi' => $formasiBidang !== null,
            'rm_from_formasi' => $formasiRm !== null,
        ];
    }

    /**
     * Map an Employee instance into an RM Level (RM-1 s.d. RM-7)
     * SSOT: DOMAIN.md Bab 4.2 & Master Data Positions
     */
    public function determineRmLevel(Employee $emp): string
    {
        // 1. Prioritaskan penetapan manual / override eksplisit dari form atau SK penugasan
        if (! empty($emp->rm_level)) {
            return $emp->rm_level;
        }

        $level = strtolower((string) $emp->level);
        $gol = strtoupper((string) $emp->golongan);
        $jabatan = strtolower((string) $emp->jabatan);
        $jobGrade = (int) $emp->job_grade;

        // Karyawan Pelaksana (Karpel) per DOMAIN.md Bab 4.1 & 4.2.4: band dari master jabatan, RM-4 bila belum tercantum
        if (
            str_contains($level, 'karpel') || str_contains($level, 'pelaksana') ||
            str_starts_with($gol, 'I/') || str_starts_with($gol, 'IA') || str_starts_with($gol, 'IB') || str_starts_with($gol, 'IC') || str_starts_with($gol, 'ID') ||
            str_starts_with($gol, 'II/') || str_starts_with($gol, 'IIA') || str_starts_with($gol, 'IIB') || str_starts_with($gol, 'IIC') || str_starts_with($gol, 'IID') ||
            ($jobGrade >= 1 && $jobGrade <= 10)
        ) {
            $masterRm = ! empty($emp->jabatan) ? Position::getAllCached()->get((string) $emp->jabatan)?->rm_level : null;

            return in_array($masterRm, Position::KARPEL_RM_LEVELS, true) ? $masterRm : 'RM-4';
        }

        // Cek master posisi jika ada pencocokan nama posisi aktif dengan rm_level eksplisit
        if (! empty($emp->jabatan)) {
            $pos = Position::getAllCached()->get((string) $emp->jabatan);
            if ($pos && ! empty($pos->rm_level)) {
                return $pos->rm_level;
            }
        }

        // RM-1: Pimpinan Puncak Unit / Senior Leadership (DOMAIN.md Bab 4.2.1)
        if (($jobGrade >= 15 && $jobGrade <= 16) || str_starts_with($gol, 'IV')) {
            return 'RM-1';
        }

        // RM-2: Middle Management / Madya (DOMAIN.md Bab 4.2.2)
        if (($jobGrade >= 13 && $jobGrade <= 14) || str_starts_with($gol, 'IIID') || str_starts_with($gol, 'IIIC')) {
            return 'RM-2';
        }

        // Fallback to Job Title matching
        $jRmNumber = $this->determineRmLevelByJobTitle($jabatan);

        return 'RM-'.$jRmNumber;
    }

    /**
     * Determine RM Level based solely on job title (Jabatan) string matching.
     * Used when evaluating potential positions where we don't have a full Employee object.
     */
    public function determineRmLevelByJobTitle(string $jabatan): int
    {
        $jabatanLower = strtolower($jabatan);

        if (
            str_contains($jabatanLower, 'manajer') ||
            str_contains($jabatanLower, 'general manager') ||
            str_contains($jabatanLower, 'kepala bagian') ||
            str_contains($jabatanLower, 'kabag') ||
            str_contains($jabatanLower, 'koordinator spi') ||
            str_contains($jabatanLower, 'senior manager') ||
            str_contains($jabatanLower, 'pimpinan')
        ) {
            return 1;
        } elseif (
            str_contains($jabatanLower, 'askep') ||
            str_contains($jabatanLower, 'asisten kepala') ||
            str_contains($jabatanLower, 'kasubag') ||
            str_contains($jabatanLower, 'kepala sub') ||
            str_contains($jabatanLower, 'masinis kepala')
        ) {
            return 2;
        } elseif (str_contains($jabatanLower, 'asisten')) {
            return 3;
        }

        return 4;
    }

    /**
     * Map an Employee instance into an Operational Field (KEU, TAN, TEK, UMU)
     */
    public function determineBidang(Employee $emp): string
    {
        // Kursi pada master formasi menentukan bidang; heuristik teks hanya cadangan
        // untuk karyawan tanpa kursi (jabatan pool, Karpel, atau data lama).
        if (filled($emp->jabatan) && filled($emp->unit_kerja)) {
            $seatBidang = $this->catalog()->seatBidang((string) $emp->unit_kerja, (string) $emp->jabatan);
            if ($seatBidang !== null) {
                return $seatBidang;
            }
        }

        $text = strtolower($emp->jabatan.' '.$emp->unit_kerja);

        if (str_contains($text, 'keu') || str_contains($text, 'akuntan') || str_contains($text, 'anggaran') || str_contains($text, 'pajak') || str_contains($text, 'kas') || str_contains($text, 'perbendaharaan')) {
            return 'KEU';
        }

        if (str_contains($text, 'tanaman') || str_contains($text, 'agronomi') || str_contains($text, 'afdeling') || str_contains($text, 'rayon') || str_contains($text, 'panen') || str_contains($text, 'kebun')) {
            return 'TAN';
        }

        if (str_contains($text, 'teknik') || str_contains($text, 'pabrik') || str_contains($text, 'pks') || str_contains($text, 'pengolahan') || str_contains($text, 'bengkel') || str_contains($text, 'instalasi') || str_contains($text, 'mesin')) {
            return 'TEK';
        }

        // Default to Umum / SDM / Hukum / Kesekretariatan
        return 'UMU';
    }

    /**
     * NIK karyawan pada satu Bidang Fungsional, memakai determineBidang() yang sama dengan kolom Bidang
     * di Daftar Karyawan agar hasil filter dan nilai kolom tidak pernah berbeda. Bidang tidak dikenal
     * menghasilkan daftar kosong.
     *
     * @return array<int, string>
     */
    public function employeeNiksByBidang(string $bidang): array
    {
        if (! in_array($bidang, MppSupplyPlan::BIDANG, true)) {
            return [];
        }

        return Employee::query()
            ->select(['nik', 'jabatan', 'unit_kerja'])
            ->cursor()
            ->filter(fn (Employee $emp) => $this->determineBidang($emp) === $bidang)
            ->map(fn (Employee $emp) => (string) $emp->nik)
            ->values()
            ->all();
    }

    /**
     * Karyawan yang menjabat pada tanggal posisi, lengkap dengan klasifikasi RM/bidang, tanggal kursi kosong,
     * dan sumber pasokan yang direalisasikannya. Dipakai bersama oleh agregasi matriks dan drilldown agar
     * angka sel selalu sama dengan daftar personilnya.
     *
     * Kursi dianggap kosong sejak Masa Bebas Tugas (MBT, 6 bulan sebelum pensiun): pejabat sudah dibebaskan
     * dari tugas sehingga formasi harus diisi pengganti (DOMAIN.md Bab 5.1 & Bab 8).
     * Dikecualikan: karyawan yang mulai bekerja setelah tanggal posisi, yang kontraknya berakhir sebelum
     * tanggal posisi, dan yang sudah memasuki MBT pada atau sebelum tanggal posisi.
     *
     * @return list<array{employee: Employee, rm: string, bidang: string, vacancy: ?Carbon, overflow: bool, hired_source: ?string, realised_on: ?Carbon}>
     */
    private function presentEmployees(Carbon $asOf): array
    {
        return $this->presentCache[$asOf->toDateString()] ??= $this->loadPresentEmployees($asOf);
    }

    /**
     * @return list<array{employee: Employee, rm: string, bidang: string, vacancy: ?Carbon, overflow: bool, hired_source: ?string, realised_on: ?Carbon}>
     */
    private function loadPresentEmployees(Carbon $asOf): array
    {
        $overflowPositions = Position::getAllCached()->where('is_overflow', true)->pluck('nama')->all();
        $yearStart = $asOf->copy()->startOfYear();
        $employees = Employee::all();
        $firstKarpim = $this->firstKarpimPlacements($employees);
        $present = [];

        foreach ($employees as $emp) {
            $tmt = $emp->tmt_bekerja ? Carbon::parse($emp->tmt_bekerja)->startOfDay() : null;
            if ($tmt !== null && $tmt->gt($asOf)) {
                continue; // Belum bekerja pada tanggal posisi
            }

            if ($emp->tanggal_berakhir_kontrak && Carbon::parse($emp->tanggal_berakhir_kontrak)->lt($asOf)) {
                continue; // Kontrak telah berakhir sebelum tanggal posisi
            }

            $vacancy = $emp->effective_tanggal_mbt?->copy()->startOfDay();
            if ($vacancy !== null && $vacancy->lte($asOf)) {
                continue; // Sudah bebas tugas pada tanggal posisi
            }

            // Pasokan terealisasi saat karyawan jalur CKP/Talent Scouting/RBB pertama kali ditempatkan sebagai
            // Karpim (CKP = promosi dari Karpel, bukan tanggal mulai bekerja); tanpa riwayat, tanggal mulai bekerja.
            $rm = $this->determineRmLevel($emp);
            $channel = self::SUPPLY_CHANNELS[$emp->jalur_masuk ?? ''] ?? null;
            $realisedOn = isset($firstKarpim[$emp->nik]) ? Carbon::parse($firstKarpim[$emp->nik])->startOfDay() : $tmt;
            $realisedThisYear = $realisedOn !== null && $realisedOn->gte($yearStart) && $realisedOn->lte($asOf);

            $present[] = [
                'employee' => $emp,
                'rm' => $rm,
                'bidang' => $this->determineBidang($emp),
                'vacancy' => $vacancy,
                'overflow' => in_array($emp->jabatan, $overflowPositions, true),
                'hired_source' => $channel !== null && $realisedThisYear && in_array($rm, MppSupplyPlan::SUPPLY_LEVELS, true) ? $channel : null,
                'realised_on' => $channel !== null ? $realisedOn : null,
            ];
        }

        return $present;
    }

    /**
     * Tanggal penempatan Karpim pertama yang sah (sudah diterapkan, tidak dibatalkan) per NIK, hanya untuk
     * karyawan jalur CKP/Talent Scouting/RBB. Satu kueri agregat, bukan per karyawan.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<string, string>
     */
    private function firstKarpimPlacements($employees): array
    {
        $niks = $employees->filter(fn (Employee $e): bool => isset(self::SUPPLY_CHANNELS[$e->jalur_masuk ?? '']))->pluck('nik')->all();
        if ($niks === []) {
            return [];
        }

        return JobHistory::query()
            ->where('is_applied', true)
            ->where('level', 'Karpim')
            ->whereIn('employee_nik', $niks)
            ->groupBy('employee_nik')
            ->selectRaw('employee_nik, MIN(tmt_awal) as first_karpim')
            ->pluck('first_karpim', 'employee_nik')
            ->all();
    }

    /**
     * Agregasi data karyawan pada posisi akhir bulan $month tahun $year (T):
     * Realisasi dan jabatan penampungan pada tanggal posisi, Pensiun T = kursi kosong (MBT) setelah tanggal
     * posisi s.d. Desember T, Pensiun T+1 = sepanjang T+1, dan pasokan yang sudah direalisasikan tahun T.
     */
    public function getPositionCounts(int $year, int $month): array
    {
        $empty = fn (): array => array_fill_keys(MppSupplyPlan::LEVELS, array_fill_keys(MppSupplyPlan::BIDANG, 0));
        $asOf = $this->asOfDate($year, $month);
        $yearEnd = $asOf->copy()->endOfYear();

        $realisasi = $pensiunT = $pensiunT1 = $advisor = $diperban = $empty();
        $hired = array_fill_keys(MppSupplyPlan::SUPPLY_SOURCES, $empty());

        foreach ($this->presentEmployees($asOf) as $row) {
            ['rm' => $rm, 'bidang' => $bidang, 'vacancy' => $vacancy] = $row;
            if (! isset($realisasi[$rm][$bidang])) {
                continue; // Hanya RM-1 s.d. RM-3 yang dihitung pada MPP
            }

            // Advisor / Diperban (overflow) dipisahkan dari Realisasi struktural
            if ($row['overflow']) {
                if ($this->isAdvisor($row)) {
                    $advisor[$rm][$bidang]++;
                } else {
                    $diperban[$rm][$bidang]++;
                }
            } else {
                $realisasi[$rm][$bidang]++;
            }

            // Karyawan overflow tetap pensiun, sehingga proyeksi pensiun berlaku untuk semuanya
            if ($vacancy !== null && $vacancy->lte($yearEnd)) {
                $pensiunT[$rm][$bidang]++;
            } elseif ($vacancy !== null && $vacancy->year === $year + 1) {
                $pensiunT1[$rm][$bidang]++;
            }

            if ($row['hired_source'] !== null) {
                $hired[$row['hired_source']][$rm][$bidang]++;
            }
        }

        return [
            'realisasi' => $realisasi,
            'pensiun_t' => $pensiunT,
            'pensiun_t1' => $pensiunT1,
            'advisor' => $advisor,
            'diperban' => $diperban,
            'hired_supply' => $hired,
        ];
    }

    /**
     * Personil di balik satu sel matriks (RM Band x bidang) pada posisi akhir bulan $month tahun $year.
     *
     * @param  string  $scope  baris yang dirinci: 'realisasi' (struktural), 'advisor', 'diperban',
     *                         'pensiun_t' (kursi kosong setelah posisi s.d. Desember T), 'pensiun_t1' (sepanjang T+1),
     *                         'supply_{sumber}' (karyawan jalur itu yang sudah ditempatkan sebagai Karpim tahun T s.d. posisi)
     * @return list<array<string, mixed>>
     */
    public function getDrilldownEmployees(string $rm, string $bidang, string $scope, int $year, int $month): array
    {
        $asOf = $this->asOfDate($year, $month);
        $matched = [];

        foreach ($this->presentEmployees($asOf) as $row) {
            if ($row['rm'] !== $rm || $row['bidang'] !== $bidang) {
                continue;
            }

            if (! in_array($scope, $this->scopesFor($row, $year), true)) {
                continue;
            }

            $emp = $row['employee'];
            $matched[] = [
                'id' => $emp->id,
                'nik' => $emp->nik,
                'nama' => $emp->nama,
                'jabatan' => $emp->jabatan,
                'unit_kerja' => $emp->unit_kerja,
                'golongan' => $emp->golongan,
                'tanggal_pensiun' => $this->dateLabel($emp->effective_tanggal_pensiun),
                'tanggal_mbt' => $this->dateLabel($emp->effective_tanggal_mbt),
                'tanggal_penempatan' => $this->dateLabel($row['realised_on']),
                'status_karyawan' => $emp->status_karyawan ?? 'Aktif',
            ];
        }

        return $matched;
    }

    /**
     * Seluruh personil di balik sel personil matriks pada posisi akhir bulan $month tahun $year, satu entri per
     * scope drilldown yang diikuti karyawan. Memakai aturan scope yang sama dengan getDrilldownEmployees(),
     * sehingga jumlah anggota selalu sama dengan angka sel.
     *
     * @return list<array{scope: string, rm_level: string, bidang: string, employee_nik: string, jabatan: ?string, unit_kerja: ?string, golongan: ?string, jalur_masuk: ?string, tanggal_penempatan: ?string, tanggal_mbt: ?string}>
     */
    public function getPositionMembers(int $year, int $month): array
    {
        $members = [];

        foreach ($this->presentEmployees($this->asOfDate($year, $month)) as $row) {
            $emp = $row['employee'];
            foreach ($this->scopesFor($row, $year) as $scope) {
                $members[] = [
                    'scope' => $scope,
                    'rm_level' => $row['rm'],
                    'bidang' => $row['bidang'],
                    'employee_nik' => (string) $emp->nik,
                    'jabatan' => $emp->jabatan,
                    'unit_kerja' => $emp->unit_kerja,
                    'golongan' => $emp->golongan,
                    'jalur_masuk' => $emp->jalur_masuk,
                    'tanggal_penempatan' => $row['realised_on']?->toDateString(),
                    'tanggal_mbt' => $emp->effective_tanggal_mbt?->toDateString(),
                ];
            }
        }

        return $members;
    }

    /**
     * Scope drilldown yang diikuti satu karyawan: realisasi/advisor/diperban, pensiun_t atau pensiun_t1, dan
     * supply_{sumber}. Hanya RM-1 s.d. RM-3 yang dihitung pada MPP.
     *
     * @param  array{employee: Employee, rm: string, bidang: string, vacancy: ?Carbon, overflow: bool, hired_source: ?string, realised_on: ?Carbon}  $row
     * @return list<string>
     */
    private function scopesFor(array $row, int $year): array
    {
        if (! in_array($row['rm'], MppSupplyPlan::LEVELS, true) || ! in_array($row['bidang'], MppSupplyPlan::BIDANG, true)) {
            return [];
        }

        $scopes = [];
        if (! $row['overflow']) {
            $scopes[] = 'realisasi';
        } else {
            $scopes[] = $this->isAdvisor($row) ? 'advisor' : 'diperban';
        }

        $vacancy = $row['vacancy'];
        if ($vacancy !== null && $vacancy->lte(Carbon::create($year, 12, 31)->endOfDay())) {
            $scopes[] = 'pensiun_t';
        } elseif ($vacancy !== null && $vacancy->year === $year + 1) {
            $scopes[] = 'pensiun_t1';
        }

        if ($row['hired_source'] !== null) {
            $scopes[] = 'supply_'.$row['hired_source'];
        }

        return $scopes;
    }

    private function isAdvisor(array $row): bool
    {
        return str_contains(strtolower((string) $row['employee']->jabatan), 'advisor');
    }

    public function dateLabel(?Carbon $date): string
    {
        return $date ? $date->format('d').' '.self::MONTHS_SHORT[$date->month].' '.$date->year : '—';
    }

    /**
     * Matriks MPP lengkap (12 baris, subtotal, total) untuk tahun perencanaan T pada posisi akhir bulan
     * $asOfMonth. Seluruh angka aktual berasal dari data karyawan; standar formasi dari kursi master dan
     * pasokan dari rencana pasokan per tahun.
     *
     * @param  int  $planningYear  Tahun perencanaan (T)
     * @param  ?int  $asOfMonth  Bulan posisi (1..12); null = bawaan defaultAsOfMonth()
     */
    public function getMppMatrix(int $planningYear, ?int $asOfMonth = null): array
    {
        $fields = MppSupplyPlan::BIDANG;
        $levels = MppSupplyPlan::LEVELS;
        $month = ($asOfMonth !== null && $asOfMonth >= 1 && $asOfMonth <= 12) ? $asOfMonth : $this->defaultAsOfMonth($planningYear);

        // Horizon T dan T+1 mengikuti filter tahun perencanaan (DESIGN.md Bab 8.1).
        $yearT = $planningYear;
        $yearT1 = $yearT + 1;

        $counts = $this->getPositionCounts($yearT, $month);
        $realisasi = $counts['realisasi'];
        $pensiunT = $counts['pensiun_t'];
        $pensiunT1 = $counts['pensiun_t1'];
        $advisor = $counts['advisor'];
        $diperban = $counts['diperban'];

        $standarFormasi = $this->standarFormasi();

        // Pasokan berasal dari rencana pasokan per tahun (DOMAIN.md Bab 8); tahun tanpa rencana dihitung 0.
        // Pasokan T = sisa rencana: karyawan jalur tersebut yang sudah masuk tahun T s.d. tanggal posisi sudah
        // tercatat di Realisasi, sehingga tidak boleh dikurangkan dua kali.
        $planT = $this->supplyPlan()->matrixFor($yearT);
        $planT1 = $this->supplyPlan()->matrixFor($yearT1);
        $prioritas = $planT['prioritas'];
        $sisa = [];
        $supplyBreakdown = [];
        foreach (MppSupplyPlan::SUPPLY_SOURCES as $source) {
            foreach ($levels as $rm) {
                foreach ($fields as $field) {
                    $rencana = $planT[$source][$rm][$field];
                    $terealisasi = $counts['hired_supply'][$source][$rm][$field];
                    $sisa[$source][$rm][$field] = max(0, $rencana - $terealisasi);
                    $supplyBreakdown[$source][$rm][$field] = ['rencana' => $rencana, 'terealisasi' => $terealisasi, 'sisa' => $sisa[$source][$rm][$field]];
                }
            }
        }
        $ckp = $sisa['ckp'];
        $talentScouting = $sisa['talent_scouting'];
        $rbb = $sisa['rbb'];
        $missingYears = array_values(array_filter([$yearT, $yearT1], fn (int $y): bool => ! $this->supplyPlan()->hasPlan($y)));

        // Calculate Kebutuhan Year T & Year T+1:
        // Formula: (Standar Formasi - Realisasi + Pensiun) - Total Pasokan (CKP + Talent Scouting + RBB)
        // Pasokan T+1 bersifat kumulatif: pasokan T ditambah pasokan T+1.
        $kebutuhanT = [];
        $kebutuhanT1 = [];
        $supplyT1Plan = [];

        foreach ($levels as $rm) {
            $kebutuhanT[$rm] = [];
            $kebutuhanT1[$rm] = [];
            foreach ($fields as $field) {
                $formasiVal = $standarFormasi[$rm][$field] ?? 0;
                $realisasiVal = $realisasi[$rm][$field] ?? 0;
                $penTVal = $pensiunT[$rm][$field] ?? 0;
                $penT1Val = $pensiunT1[$rm][$field] ?? 0;
                $supplyT = $ckp[$rm][$field] + $talentScouting[$rm][$field] + $rbb[$rm][$field];
                $supplyT1 = $planT1['ckp'][$rm][$field] + $planT1['talent_scouting'][$rm][$field] + $planT1['rbb'][$rm][$field];
                $supplyT1Plan[$rm][$field] = $supplyT1;

                // Standard enterprise MPP formula:
                $kebutuhanT[$rm][$field] = ($formasiVal - $realisasiVal + $penTVal) - $supplyT;
                $kebutuhanT1[$rm][$field] = ($formasiVal - $realisasiVal + $penTVal + $penT1Val) - ($supplyT + $supplyT1);
            }
        }

        // Apa yang dibuka sel tiap baris: daftar personil yang persis dihitung sel itu (scope drilldown),
        // rincian rumus ('formula'), atau tidak ada (standar formasi dan rencana pasokan tanpa personil).
        $asOfLabel = $this->monthLabel($month, $yearT);

        // Define row configuration with dynamic Year T & Year T+1 labels
        $rowsConfig = [
            1 => ['label' => 'Standar Formasi', 'data' => $standarFormasi, 'highlight' => 'formasi', 'drill' => null],
            2 => ['label' => 'Realisasi per '.$asOfLabel, 'data' => $realisasi, 'highlight' => 'realisasi', 'drill' => 'realisasi'],
            3 => ['label' => 'Jabatan Prioritas Diisi', 'data' => $prioritas, 'highlight' => 'neutral', 'drill' => null],
            4 => ['label' => 'Pensiun sd Des '.$yearT, 'data' => $pensiunT, 'highlight' => 'neutral', 'drill' => 'pensiun_t'],
            5 => ['label' => 'Pensiun sd Des '.$yearT1, 'data' => $pensiunT1, 'highlight' => 'neutral', 'drill' => 'pensiun_t1'],
            6 => ['label' => 'Sisa CKP Internal', 'data' => $ckp, 'highlight' => 'supply', 'drill' => 'supply_ckp'],
            7 => ['label' => 'Sisa Talent Scouting', 'data' => $talentScouting, 'highlight' => 'supply', 'drill' => 'supply_talent_scouting'],
            8 => ['label' => 'Sisa RBB '.$yearT, 'data' => $rbb, 'highlight' => 'supply', 'drill' => 'supply_rbb'],
            9 => ['label' => 'Kebutuhan '.$yearT, 'data' => $kebutuhanT, 'highlight' => 'kebutuhan', 'drill' => 'formula'],
            10 => ['label' => 'Kebutuhan '.$yearT1, 'data' => $kebutuhanT1, 'highlight' => 'kebutuhan', 'drill' => 'formula'],
            11 => ['label' => 'Jabatan Fungsional (Advisor)', 'data' => $advisor, 'highlight' => 'fungsional', 'drill' => 'advisor'],
            12 => ['label' => 'Jabatan Fungsional Pembantu (Diperban)', 'data' => $diperban, 'highlight' => 'fungsional', 'drill' => 'diperban'],
        ];

        // Compute subtotals & grand totals for every row
        $matrix = [];
        $totalFormasi = 0;
        $totalRealisasi = 0;
        $totalPensiun = 0;
        $totalKebutuhanT = 0;

        foreach ($rowsConfig as $no => $item) {
            $rowData = [
                'no' => $no,
                'label' => $item['label'],
                'highlight' => $item['highlight'],
                'drill' => $item['drill'],
                'levels' => [],
                'grand_total' => 0,
            ];

            $grandTotal = 0;

            foreach ($levels as $rm) {
                $subtotal = 0;
                $levelFields = [];

                foreach ($fields as $field) {
                    $val = $item['data'][$rm][$field] ?? 0;
                    $levelFields[$field] = $val;
                    $subtotal += $val;
                }

                $rowData['levels'][$rm] = [
                    'fields' => $levelFields,
                    'subtotal' => $subtotal,
                ];

                // Kebutuhan: kekurangan dan surplus dijumlah terpisah agar tidak saling meniadakan.
                if ($item['highlight'] === 'kebutuhan') {
                    $rowData['levels'][$rm]['shortage'] = array_sum(array_filter($levelFields, fn (int $v): bool => $v > 0));
                    $rowData['levels'][$rm]['surplus'] = -array_sum(array_filter($levelFields, fn (int $v): bool => $v < 0));
                }

                $grandTotal += $subtotal;
            }

            $rowData['grand_total'] = $grandTotal;

            // Sel pasokan dapat dibuka bila ada rencana atau realisasi, termasuk sisa 0 karena realisasi melebihi rencana.
            if (str_starts_with((string) $item['drill'], 'supply_')) {
                $source = substr($item['drill'], strlen('supply_'));
                foreach ($levels as $rm) {
                    foreach ($fields as $field) {
                        $cell = $supplyBreakdown[$source][$rm][$field];
                        $rowData['cell_drillable'][$rm][$field] = $cell['rencana'] > 0 || $cell['terealisasi'] > 0;
                    }
                }
            }

            if ($item['highlight'] === 'kebutuhan') {
                $rowData['shortage_total'] = array_sum(array_column($rowData['levels'], 'shortage'));
                $rowData['surplus_total'] = array_sum(array_column($rowData['levels'], 'surplus'));
            }

            if ($no === 1) {
                $totalFormasi = $rowData['grand_total'];
            }
            if ($no === 2) {
                $totalRealisasi = $rowData['grand_total'];
            }
            if ($no === 4) {
                $totalPensiun += $rowData['grand_total'];
            }
            if ($no === 5) {
                $totalPensiun += $rowData['grand_total'];
            }
            if ($no === 9) {
                $totalKebutuhanT = $rowData['grand_total'];
            }

            $matrix[$no] = $rowData;
        }

        // Summary KPI stats
        $fillRate = $totalFormasi > 0 ? round(($totalRealisasi / $totalFormasi) * 100, 1) : 0;

        return [
            'matrix' => $matrix,
            // Rencana vs realisasi per sel pasokan: sisa = max(0, rencana - terealisasi).
            'supply_breakdown' => $supplyBreakdown,
            // Pasokan rencana T+1 (CKP + Talent Scouting + RBB) per sel, dipakai rumus Kebutuhan T+1.
            'supply_t1' => $supplyT1Plan,
            'stats' => [
                'total_formasi' => $totalFormasi,
                'total_realisasi' => $totalRealisasi,
                'fill_rate' => $fillRate,
                'total_pensiun' => $totalPensiun,
                'total_kebutuhan' => $totalKebutuhanT,
                'total_kekurangan' => $matrix[9]['shortage_total'],
                'total_surplus' => $matrix[9]['surplus_total'],
                'supply_plan_missing' => $missingYears !== [],
                'supply_plan_missing_years' => $missingYears,
                'supply_plan_provenance' => array_values(array_filter([
                    $this->supplyPlan()->provenance($yearT),
                    $this->supplyPlan()->provenance($yearT1),
                ])),
                'as_of_month' => $month,
                'as_of_label' => $asOfLabel,
                // Jendela proyeksi pensiun: bulan setelah posisi s.d. Desember T+1.
                'pensiun_window_label' => ($month === 12 ? $this->monthLabel(1, $yearT1) : $this->monthLabel($month + 1, $yearT)).' – Des '.$yearT1,
                'planning_year' => $yearT,
                'planning_year_next' => $yearT1,
            ],
            'levels' => $levels,
            'fields' => $fields,
        ];
    }
}
