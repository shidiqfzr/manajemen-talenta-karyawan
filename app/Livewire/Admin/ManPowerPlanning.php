<?php

namespace App\Livewire\Admin;

use App\Exceptions\MppPeriodRuleViolationException;
use App\Models\MppPeriod;
use App\Models\MppSupplyPlan;
use App\Services\ManPowerPlanningService;
use App\Services\Mpp\MppPeriodService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ManPowerPlanning extends Component
{
    public string $filterBidang = 'ALL';    // 'ALL', 'KEU', 'TAN', 'TEK', 'UMU'

    public string $filterLevel = 'ALL';     // 'ALL', 'RM-1', 'RM-2', 'RM-3'

    public int $planningYear;               // Dynamic Planning Horizon Year T

    /** Bulan "Posisi per" (1..12): Realisasi dihitung pada akhir bulan ini. */
    public int $asOfMonth;

    /** Nilai sebelum perubahan dari wire:model, untuk dikembalikan bila nilai baru tidak sah. */
    private ?int $previousYear = null;

    private ?int $previousMonth = null;

    /** Satu instance service per permintaan agar pembacaan pegawai dipakai bersama (lihat ManPowerPlanningService). */
    private ?ManPowerPlanningService $mppService = null;

    private function mpp(): ManPowerPlanningService
    {
        return $this->mppService ??= app(ManPowerPlanningService::class);
    }

    /** Periode bulan yang sedang dilihat (null bila belum pernah direkam); dibaca sekali per permintaan. */
    private ?MppPeriod $period = null;

    private bool $periodResolved = false;

    private ?array $liveData = null;

    /** '' = tidak ada panel, 'close' = konfirmasi tutup periode, 'reopen' = konfirmasi buka kembali. */
    public string $periodAction = '';

    public string $periodNote = '';

    public string $periodReason = '';

    public ?string $periodError = null;

    /** Waktu rekam draf (Y-m-d H:i:s) saat panel tutup dibuka; dipakai MppPeriodService::close() sebagai bukti tinjauan. */
    #[Locked]
    public ?string $periodCapturedAt = null;

    private const BIDANG = ['KEU', 'TAN', 'TEK', 'UMU'];

    private const LEVELS = ['RM-1', 'RM-2', 'RM-3'];

    // Drilldown modal/drawer state
    public bool $showDrilldown = false;

    public string $drilldownTitle = '';

    public string $drilldownIndicator = '';

    public string $drilldownRm = '';

    public string $drilldownBidang = '';

    public array $drilldownEmployees = [];

    public ?array $drilldownFormula = null;

    /** 'people' = daftar personil sel, 'supply' = rencana vs realisasi pasokan, 'formula' = rincian rumus Kebutuhan. */
    public string $drilldownMode = '';

    /** Rincian sel pasokan: rencana, terealisasi, sisa. */
    public ?array $drilldownSupply = null;

    /** Konfirmasi singkat setelah rencana pasokan disimpan dari editor. */
    public ?string $flash = null;

    /** Matriks dihitung ulang (render) setelah rencana pasokan disimpan dari editor. */
    protected $listeners = ['mpp-supply-plan-saved' => 'onSupplyPlanSaved'];

    public function onSupplyPlanSaved(int $year): void
    {
        $this->flash = "Rencana pasokan {$year} berhasil disimpan.";
    }

    public function mount(): void
    {
        $this->planningYear = min(max(Carbon::now()->year, ManPowerPlanningService::PLANNING_YEAR_MIN), ManPowerPlanningService::PLANNING_YEAR_MAX);
        $this->asOfMonth = $this->mpp()->defaultAsOfMonth($this->planningYear);
    }

    public function setFilterBidang(string $bidang): void
    {
        if ($bidang === 'ALL' || in_array($bidang, self::BIDANG, true)) {
            $this->filterBidang = $bidang;
        }
    }

    public function setFilterLevel(string $level): void
    {
        if ($level === 'ALL' || in_array($level, self::LEVELS, true)) {
            $this->filterLevel = $level;
        }
    }

    public function setPlanningYear(int $year): void
    {
        if ($this->isValidYear($year)) {
            $this->planningYear = $year;
            $this->asOfMonth = $this->mpp()->defaultAsOfMonth($year);
            $this->resetPeriodPanel();
        }
    }

    public function setAsOfMonth(int $month): void
    {
        if ($month >= 1 && $month <= 12) {
            $this->asOfMonth = $month;
            $this->resetPeriodPanel();
        }
    }

    // Pemilih tahun dan bulan memakai wire:model.live; nilai di luar rentang dikembalikan ke nilai sebelumnya.
    public function updatingPlanningYear(): void
    {
        $this->previousYear = $this->planningYear;
    }

    public function updatedPlanningYear(mixed $value): void
    {
        if (! $this->isValidYear((int) $value)) {
            $this->planningYear = $this->previousYear;

            return;
        }

        $this->asOfMonth = $this->mpp()->defaultAsOfMonth($this->planningYear);
        $this->resetPeriodPanel();
    }

    public function updatingAsOfMonth(): void
    {
        $this->previousMonth = $this->asOfMonth;
    }

    public function updatedAsOfMonth(mixed $value): void
    {
        if ((int) $value < 1 || (int) $value > 12) {
            $this->asOfMonth = $this->previousMonth;

            return;
        }

        $this->resetPeriodPanel();
    }

    private function isValidYear(int $year): bool
    {
        return $year >= ManPowerPlanningService::PLANNING_YEAR_MIN && $year <= ManPowerPlanningService::PLANNING_YEAR_MAX;
    }

    /**
     * Buka rincian satu sel. Baris menentukan isinya (ManPowerPlanningService, kunci 'drill'): daftar personil
     * yang persis dihitung sel itu, atau rincian rumus untuk baris Kebutuhan. Sel angka acuan tidak dibuka.
     */
    public function openDrilldown(string $rm, string $bidang, int $row): void
    {
        if (! in_array($rm, self::LEVELS, true) || ! in_array($bidang, self::BIDANG, true)) {
            return;
        }

        $mpp = $this->mppData();
        $period = $this->period();
        $matrix = $mpp['matrix'];
        $drill = $matrix[$row]['drill'] ?? null;
        if ($drill === null) {
            return;
        }

        $cell = fn (int $no): int => $matrix[$no]['levels'][$rm]['fields'][$bidang] ?? 0;

        if ($drill === 'formula') {
            $isT = $row === 9;
            $s = $cell(6) + $cell(7) + $cell(8);
            if (! $isT) {
                // Pasokan T+1 kumulatif, sama dengan rumus sel Kebutuhan T+1.
                $s += $mpp['supply_t1'][$rm][$bidang];
            }
            $k = $cell($row);

            $this->drilldownFormula = [
                'formasi' => $cell(1),
                'realisasi' => $cell(2),
                'pensiun' => $isT ? $cell(4) : $cell(4) + $cell(5),
                'pasokan' => $s,
                'kebutuhan' => $k,
                'is_surplus' => $k <= 0,
            ];
            $this->drilldownEmployees = [];
            $this->drilldownTitle = 'Rincian Formula Kebutuhan';
        } else {
            $this->drilldownFormula = null;
            // Bulan berisi snapshot: personil dibaca dari salinan periode, bukan dari data pegawai terkini.
            $this->drilldownEmployees = $period
                ? app(MppPeriodService::class)->membersFor($period, $drill, $rm, $bidang)
                : $this->mpp()->getDrilldownEmployees($rm, $bidang, $drill, $this->planningYear, $this->asOfMonth);
            $this->drilldownTitle = 'Rincian Personil Formasi';
        }

        $this->drilldownSupply = null;
        if (str_starts_with($drill, 'supply_')) {
            $source = substr($drill, strlen('supply_'));
            $this->drilldownSupply = $mpp['supply_breakdown'][$source][$rm][$bidang];
            $this->drilldownTitle = 'Rincian Pasokan '.MppSupplyPlan::SOURCES[$source];
        }

        $this->drilldownMode = match (true) {
            $drill === 'formula' => 'formula',
            $this->drilldownSupply !== null => 'supply',
            default => 'people',
        };
        $this->drilldownRm = $rm;
        $this->drilldownBidang = $bidang;
        $this->drilldownIndicator = $matrix[$row]['label'];
        $this->showDrilldown = true;
    }

    public function closeDrilldown(): void
    {
        $this->showDrilldown = false;
        $this->drilldownEmployees = [];
        $this->drilldownTitle = '';
        $this->drilldownIndicator = '';
        $this->drilldownRm = '';
        $this->drilldownBidang = '';
        $this->drilldownFormula = null;
        $this->drilldownSupply = null;
        $this->drilldownMode = '';
    }

    public function exportDrilldownCsv()
    {
        $this->authorizeAdmin();

        if (empty($this->drilldownEmployees)) {
            return;
        }

        $employees = $this->drilldownEmployees;

        // Berkas memuat NIK & nama pegawai (UU PDP): catat siapa yang mengunduh, tanpa data pribadinya.
        Log::info('Ekspor rincian personil MPP', [
            'user_id' => auth()->id(),
            'rm' => $this->drilldownRm,
            'bidang' => $this->drilldownBidang,
            'indikator' => $this->drilldownIndicator,
            'tahun' => $this->planningYear,
            'posisi_per' => $this->asOfKey(),
            'jumlah_baris' => count($employees),
        ] + $this->periodLogContext());

        $rm = $this->drilldownRm;
        $bidang = $this->drilldownBidang;
        $indicator = preg_replace('/[^A-Za-z0-9_]/', '_', $this->drilldownIndicator);
        $fileName = "Personil_{$rm}_{$bidang}_{$indicator}_".date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($employees) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            fputcsv($handle, ['No.', 'NIK', 'Nama Lengkap', 'Jabatan', 'Unit Kerja', 'Golongan', 'Tanggal Penempatan Karpim', 'Tanggal MBT', 'Tanggal Pensiun', 'Status']);

            $no = 1;
            foreach ($employees as $emp) {
                fputcsv($handle, [
                    $no++,
                    $emp['nik'],
                    $emp['nama'],
                    $emp['jabatan'],
                    $emp['unit_kerja'],
                    $emp['golongan'] ?? '—',
                    $emp['tanggal_penempatan'] ?? '—',
                    $emp['tanggal_mbt'],
                    $emp['tanggal_pensiun'],
                    $emp['status_karyawan'],
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function exportCsv()
    {
        $this->authorizeAdmin();

        $matrix = $this->mppData()['matrix'];
        $yearT = $this->planningYear;
        $yearT1 = $yearT + 1;
        $fileName = 'mpp_perencanaan_formasi_'.$yearT.'_'.$yearT1.'_posisi_'.$this->asOfKey().$this->periodFileSuffix().'_'.Carbon::now()->format('Ymd_His').'.csv';

        Log::info('Ekspor matriks MPP', [
            'user_id' => auth()->id(),
            'tahun' => $yearT,
            'posisi_per' => $this->asOfKey(),
        ] + $this->periodLogContext());

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($matrix) {
            $handle = fopen('php://output', 'w');
            // Write BOM for proper Excel UTF-8 display
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header Row
            fputcsv($handle, [
                'No.',
                'URAIAN INDIKATOR',
                'RM-1 KEU',
                'RM-1 TAN',
                'RM-1 TEK',
                'RM-1 UMU',
                'JUMLAH RM-1',
                'RM-2 KEU',
                'RM-2 TAN',
                'RM-2 TEK',
                'RM-2 UMU',
                'JUMLAH RM-2',
                'RM-3 KEU',
                'RM-3 TAN',
                'RM-3 TEK',
                'RM-3 UMU',
                'JUMLAH RM-3',
                'TOTAL',
                'TOTAL KEKURANGAN',
                'TOTAL SURPLUS',
            ]);

            foreach ($matrix as $row) {
                fputcsv($handle, [
                    $row['no'],
                    $row['label'],
                    $row['levels']['RM-1']['fields']['KEU'] ?? 0,
                    $row['levels']['RM-1']['fields']['TAN'] ?? 0,
                    $row['levels']['RM-1']['fields']['TEK'] ?? 0,
                    $row['levels']['RM-1']['fields']['UMU'] ?? 0,
                    $row['levels']['RM-1']['subtotal'] ?? 0,
                    $row['levels']['RM-2']['fields']['KEU'] ?? 0,
                    $row['levels']['RM-2']['fields']['TAN'] ?? 0,
                    $row['levels']['RM-2']['fields']['TEK'] ?? 0,
                    $row['levels']['RM-2']['fields']['UMU'] ?? 0,
                    $row['levels']['RM-2']['subtotal'] ?? 0,
                    $row['levels']['RM-3']['fields']['KEU'] ?? 0,
                    $row['levels']['RM-3']['fields']['TAN'] ?? 0,
                    $row['levels']['RM-3']['fields']['TEK'] ?? 0,
                    $row['levels']['RM-3']['fields']['UMU'] ?? 0,
                    $row['levels']['RM-3']['subtotal'] ?? 0,
                    $row['grand_total'] ?? 0,
                    $row['shortage_total'] ?? '',
                    $row['surplus_total'] ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /** Data matriks: salinan periode bila bulan ini sudah direkam, selain itu dihitung dari data pegawai. */
    private function mppData(): array
    {
        $snapshot = $this->period()?->snapshot;
        if ($snapshot !== null) {
            return $snapshot;
        }

        return $this->liveData ??= $this->mpp()->getMppMatrix($this->planningYear, $this->asOfMonth);
    }

    private function period(): ?MppPeriod
    {
        if (! $this->periodResolved) {
            $this->period = app(MppPeriodService::class)->find($this->planningYear, $this->asOfMonth)?->load(['capturer', 'closer']);
            $this->periodResolved = true;
        }

        return $this->period;
    }

    /** Setelah menulis ke periode, bacaan periode dan matriks pada permintaan ini harus diulang. */
    private function forgetPeriod(): void
    {
        $this->period = null;
        $this->periodResolved = false;
        $this->liveData = null;
    }

    /** closed | draft (ada salinan), unclosed (bulan lampau belum pernah direkam), live (bulan berjalan atau mendatang). */
    private function periodState(): string
    {
        return app(MppPeriodService::class)->stateFor($this->planningYear, $this->asOfMonth);
    }

    /** Akhiran nama berkas ekspor untuk bulan berisi snapshot, mis. "_ditutup_rev1". */
    private function periodFileSuffix(): string
    {
        $period = $this->period();

        return $period ? '_'.$this->periodLabel($period).'_rev'.$period->revision : '';
    }

    /** @return array{periode?: string, revisi?: int} */
    private function periodLogContext(): array
    {
        $period = $this->period();

        return $period ? ['periode' => $this->periodLabel($period), 'revisi' => $period->revision] : [];
    }

    private function periodLabel(MppPeriod $period): string
    {
        return $period->status === MppPeriod::STATUS_CLOSED ? 'ditutup' : 'draf';
    }

    /** "dd Bln YYYY HH:ii" dengan nama bulan Indonesia, tidak bergantung pada locale aplikasi. */
    private function stamp(?Carbon $at): ?string
    {
        return $at ? $this->mpp()->dateLabel($at).' '.$at->format('H:i') : null;
    }

    public function capturePeriod(): void
    {
        $this->authorizeAdmin();
        $this->flash = null;
        $this->periodError = null;

        try {
            app(MppPeriodService::class)->capture($this->planningYear, $this->asOfMonth, auth()->user());
        } catch (MppPeriodRuleViolationException $e) {
            $this->periodError = $e->getMessage();

            return;
        } finally {
            $this->forgetPeriod();
        }

        $this->flash = 'Draf periode '.$this->mpp()->monthLabel($this->asOfMonth, $this->planningYear).' berhasil direkam.';
    }

    public function requestPeriodAction(string $action): void
    {
        $this->authorizeAdmin();

        $this->resetPeriodPanel();
        if (in_array($action, ['close', 'reopen'], true)) {
            $this->periodAction = $action;
            if ($action === 'close') {
                $this->periodCapturedAt = app(MppPeriodService::class)
                    ->find($this->planningYear, $this->asOfMonth)?->captured_at?->format('Y-m-d H:i:s');
            }
        }
    }

    public function confirmPeriodAction(): void
    {
        $this->authorizeAdmin();
        $this->periodError = null;
        $service = app(MppPeriodService::class);
        $label = $this->mpp()->monthLabel($this->asOfMonth, $this->planningYear);
        $action = $this->periodAction;

        try {
            match ($action) {
                'close' => $service->close($this->planningYear, $this->asOfMonth, $this->periodNote, auth()->user(), $this->periodCapturedAt),
                'reopen' => $service->reopen($this->planningYear, $this->asOfMonth, $this->periodReason, auth()->user()),
                default => null,
            };
        } catch (MppPeriodRuleViolationException $e) {
            // Panel tetap terbuka agar isian tidak hilang.
            $this->periodError = $e->getMessage();

            return;
        } finally {
            $this->forgetPeriod();
        }

        $this->resetPeriodPanel();
        $this->flash = match ($action) {
            'close' => "Periode {$label} ditutup.",
            'reopen' => "Periode {$label} dibuka kembali sebagai draf.",
            default => null,
        };
    }

    public function cancelPeriodAction(): void
    {
        $this->authorizeAdmin();
        $this->resetPeriodPanel();
    }

    private function resetPeriodPanel(): void
    {
        $this->periodAction = '';
        $this->periodNote = '';
        $this->periodReason = '';
        $this->periodError = null;
        $this->periodCapturedAt = null;
    }

    /** Posisi per dalam format YYYY-MM untuk nama berkas dan log audit. */
    private function asOfKey(): string
    {
        return sprintf('%d-%02d', $this->planningYear, $this->asOfMonth);
    }

    /** Livewire tidak menerapkan ulang middleware role:admin pada permintaan pembaruan komponen. */
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }

    public function render()
    {
        $mppData = $this->mppData();
        $period = $this->period();

        return view('livewire.admin.man-power-planning', [
            'period' => $period,
            'periodState' => $this->periodState(),
            'periodStamps' => [
                'captured' => $this->stamp($period?->captured_at),
                'closed' => $this->stamp($period?->closed_at),
            ],
            'periodLabel' => $this->mpp()->monthLabel($this->asOfMonth, $this->planningYear),
            'matrix' => $mppData['matrix'],
            'stats' => $mppData['stats'],
            'levels' => $mppData['levels'],
            'fields' => $mppData['fields'],
            'rmBands' => ManPowerPlanningService::RM_BANDS,
            'months' => ManPowerPlanningService::MONTHS,
            'availableYears' => range(ManPowerPlanningService::PLANNING_YEAR_MIN, ManPowerPlanningService::PLANNING_YEAR_MAX),
        ]);
    }
}
