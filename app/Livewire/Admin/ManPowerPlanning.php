<?php

namespace App\Livewire\Admin;

use App\Models\Employee;
use App\Services\ManPowerPlanningService;
use Carbon\Carbon;
use Livewire\Component;

class ManPowerPlanning extends Component
{
    public string $sourceMode = 'baseline'; // 'baseline' or 'live'

    public string $filterBidang = 'ALL';    // 'ALL', 'KEU', 'TAN', 'TEK', 'UMU'

    public string $filterLevel = 'ALL';     // 'ALL', 'RM-1', 'RM-2', 'RM-3'

    public int $planningYear = 2026;        // Dynamic Planning Horizon Year T

    // Drilldown modal/drawer state
    public bool $showDrilldown = false;

    public string $drilldownTitle = '';

    public string $drilldownIndicator = '';

    public string $drilldownRm = '';

    public string $drilldownBidang = '';

    public array $drilldownEmployees = [];

    public ?array $drilldownFormula = null;

    public function setSourceMode(string $mode): void
    {
        $this->sourceMode = in_array($mode, ['baseline', 'live']) ? $mode : 'baseline';
    }

    public function setFilterBidang(string $bidang): void
    {
        $this->filterBidang = $bidang;
    }

    public function setFilterLevel(string $level): void
    {
        $this->filterLevel = $level;
    }

    public function setPlanningYear(int $year): void
    {
        $this->planningYear = $year;
    }

    public function openDrilldown(string $rm, string $bidang, string $indicator): void
    {
        $service = app(ManPowerPlanningService::class);
        $allEmployees = Employee::all();
        $yearT = Carbon::now()->year;
        $yearT1 = $yearT + 1;

        $indicatorLower = strtolower($indicator);
        $isKebutuhan = str_contains($indicatorLower, 'kebutuhan');
        $isPensiunT = str_contains($indicator, (string) $yearT) && str_contains($indicatorLower, 'pensiun');
        $isPensiunT1 = str_contains($indicator, (string) $yearT1) && str_contains($indicatorLower, 'pensiun');

        // Check if formula breakdown applies (for Kebutuhan rows)
        if ($isKebutuhan) {
            $mppData = $service->getMppMatrix($this->sourceMode, $this->planningYear);
            $matrix = $mppData['matrix'];
            $f = $matrix[1]['levels'][$rm]['fields'][$bidang] ?? 0;
            $r = $matrix[2]['levels'][$rm]['fields'][$bidang] ?? 0;
            $isT = str_contains($indicator, (string) $yearT);
            $p = $isT
                ? ($matrix[4]['levels'][$rm]['fields'][$bidang] ?? 0)
                : (($matrix[4]['levels'][$rm]['fields'][$bidang] ?? 0) + ($matrix[5]['levels'][$rm]['fields'][$bidang] ?? 0));
            $s = ($matrix[6]['levels'][$rm]['fields'][$bidang] ?? 0) + ($matrix[7]['levels'][$rm]['fields'][$bidang] ?? 0) + ($matrix[8]['levels'][$rm]['fields'][$bidang] ?? 0);
            $k = $isT
                ? ($matrix[9]['levels'][$rm]['fields'][$bidang] ?? 0)
                : ($matrix[10]['levels'][$rm]['fields'][$bidang] ?? 0);

            $this->drilldownFormula = [
                'formasi' => $f,
                'realisasi' => $r,
                'pensiun' => $p,
                'pasokan' => $s,
                'kebutuhan' => $k,
                'is_surplus' => $k <= 0,
            ];
        } else {
            $this->drilldownFormula = null;
        }

        $matched = [];
        foreach ($allEmployees as $emp) {
            $empRm = $service->determineRmLevel($emp);
            $empBidang = $service->determineBidang($emp);

            if ($empRm !== $rm || $empBidang !== $bidang) {
                continue;
            }

            // Check if employee was hired by the filter year
            $filterYear = $this->planningYear;
            $tmtYear = $emp->tmt_bekerja ? Carbon::parse($emp->tmt_bekerja)->year : $filterYear;
            if ($tmtYear > $filterYear) {
                continue; // Hired after the filter year
            }

            // Check retirement year
            $pYear = null;
            if ($emp->tanggal_pensiun) {
                $pYear = Carbon::parse($emp->tanggal_pensiun)->year;
            } elseif ($emp->tanggal_lahir) {
                $pYear = Carbon::parse($emp->tanggal_lahir)->addYears(56)->year;
            }

            if ($pYear !== null && $pYear < $filterYear) {
                continue; // Retired before the filter year
            }

            if ($isPensiunT && ! ($pYear !== null && $pYear <= $yearT)) {
                continue;
            }
            if ($isPensiunT1 && ! ($pYear !== null && $pYear === $yearT1)) {
                continue;
            }

            $pensiunDate = $emp->tanggal_pensiun
                ? Carbon::parse($emp->tanggal_pensiun)
                : ($emp->tanggal_lahir ? Carbon::parse($emp->tanggal_lahir)->addYears(56) : null);

            $matched[] = [
                'id' => $emp->id,
                'nik' => $emp->nik,
                'nama' => $emp->nama,
                'jabatan' => $emp->jabatan,
                'unit_kerja' => $emp->unit_kerja,
                'golongan' => $emp->golongan,
                'tanggal_pensiun' => $pensiunDate ? $pensiunDate->translatedFormat('d M Y') : '—',
                'status_karyawan' => $emp->status_karyawan ?? 'Aktif',
            ];
        }

        $this->drilldownTitle = 'Rincian Personil Formasi';
        $this->drilldownRm = $rm;
        $this->drilldownBidang = $bidang;
        $this->drilldownIndicator = $indicator;
        $this->drilldownEmployees = $matched;
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
    }

    public function exportDrilldownCsv()
    {
        if (empty($this->drilldownEmployees)) {
            return;
        }

        $employees = $this->drilldownEmployees;
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

            fputcsv($handle, ['No.', 'NIK', 'Nama Lengkap', 'Jabatan', 'Unit Kerja', 'Golongan', 'Tanggal Pensiun', 'Status']);

            $no = 1;
            foreach ($employees as $emp) {
                fputcsv($handle, [
                    $no++,
                    $emp['nik'],
                    $emp['nama'],
                    $emp['jabatan'],
                    $emp['unit_kerja'],
                    $emp['golongan'] ?? '—',
                    $emp['tanggal_pensiun'],
                    $emp['status_karyawan'],
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function exportCsv(ManPowerPlanningService $service)
    {
        $mppData = $service->getMppMatrix($this->sourceMode, $this->planningYear);
        $matrix = $mppData['matrix'];
        $filterYear = $this->planningYear;
        $yearT = Carbon::now()->year;
        $yearT1 = $yearT + 1;
        $fileName = 'MPP_PTPN_IV_'.$yearT.'-'.$yearT1.'_Filter_'.$filterYear.'_'.strtoupper($this->sourceMode).'_'.date('Ymd_His').'.csv';

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
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function render(ManPowerPlanningService $service)
    {
        $mppData = $service->getMppMatrix($this->sourceMode, $this->planningYear);

        return view('livewire.admin.man-power-planning', [
            'matrix' => $mppData['matrix'],
            'stats' => $mppData['stats'],
            'levels' => $mppData['levels'],
            'fields' => $mppData['fields'],
            'availableYears' => range(2024, 2031),
        ]);
    }
}
