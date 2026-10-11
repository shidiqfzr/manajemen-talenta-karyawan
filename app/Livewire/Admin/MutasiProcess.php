<?php

namespace App\Livewire\Admin;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Services\ManPowerPlanningService;
use App\Services\MutasiService;
use Livewire\Component;

class MutasiProcess extends Component
{
    // ── Mutation Type & Legal ──────────────────────────────────────────────
    public string $jenisMutasi = 'PROMOSI';

    public string $statusJabatan = 'Definitif';

    public string $tmtAwal = '';

    public string $nomorSk = '';

    public string $tanggalSk = '';

    public string $catatan = '';

    public string $tmtAkhirEstimasi = '';

    // ── Employee (source) ──────────────────────────────────────────────────
    public string $employeeNik = '';

    public string $sourceUnitFilter = '';

    public ?array $employeeDetails = null;

    // ── Target position ────────────────────────────────────────────────────
    public string $targetJabatan = '';

    public string $targetUnitKerja = '';

    // ── Live impact analysis ───────────────────────────────────────────────
    /** null = not yet analysed, array = analysis result */
    public ?array $targetAnalysis = null;

    /** Explicit confirmation required when target is occupied (swap) */
    public bool $swapConfirmed = false;

    // ── Diperban (Sanksi) ──────────────────────────────────────────────────
    public bool $isTargetDiperban = false;

    public bool $isTargetOverflow = false;

    public string $tanggalBerakhirSanksi = '';

    public function mount(?string $jabatan = null, ?string $unit = null, ?string $nik = null): void
    {
        $this->tmtAwal = now()->toDateString();
        $this->tanggalSk = now()->toDateString();

        if ($nik) {
            $this->employeeNik = $nik;
            $this->updatedEmployeeNik($nik); // Trigger the employee info load
        }

        // Deep-link pre-fill from vacancy monitoring page
        if ($jabatan) {
            $this->targetJabatan = $jabatan;
        }
        if ($unit) {
            $this->targetUnitKerja = $unit;
        }

        if ($jabatan && $unit) {
            $this->runAnalysis();
        }
    }

    // ── Livewire lifecycle hooks ───────────────────────────────────────────

    public function updatedJenisMutasi(): void
    {
        if (in_array($this->jenisMutasi, ['SANKSI_INDISIPLINER', 'PENUGASAN_KHUSUS'])) {
            $this->statusJabatan = 'Definitif';
            $this->tmtAkhirEstimasi = '';
        }
        $this->resetTarget();
    }

    public function updatedEmployeeNik(string $value): void
    {
        $this->employeeDetails = $this->resolveEmployee($value);
        $this->resetTarget();
    }

    public function updatedSourceUnitFilter(): void
    {
        $this->employeeNik = '';
        $this->employeeDetails = null;
        $this->resetTarget();
    }

    public function updatedTargetJabatan(): void
    {
        $this->swapConfirmed = false;
        $this->runAnalysis();
    }

    public function updatedTargetUnitKerja(): void
    {
        $this->resetTarget(resetUnit: false);
    }

    private function resetTarget(bool $resetUnit = true): void
    {
        $this->targetJabatan = '';
        if ($resetUnit) {
            $this->targetUnitKerja = '';
        }
        $this->targetAnalysis = null;
        $this->swapConfirmed = false;
        $this->isTargetDiperban = false;
        $this->isTargetOverflow = false;
        $this->tanggalBerakhirSanksi = '';
    }

    // ── Submit ─────────────────────────────────────────────────────────────

    public function submitMutasi(MutasiService $service): void
    {
        $this->validate([
            'jenisMutasi' => 'required|string',
            'statusJabatan' => 'required|in:Definitif,Pj,Plt',
            'employeeNik' => 'required|exists:employees,nik',
            'targetJabatan' => 'required|string',
            'targetUnitKerja' => 'required|string',
            'tmtAwal' => 'required|date',
            'nomorSk' => 'required|string|max:100',
            'tanggalSk' => 'required|date',
            'catatan' => 'required|string|max:500',
            'tmtAkhirEstimasi' => 'nullable|date|after_or_equal:tmtAwal',
            'tanggalBerakhirSanksi' => 'exclude_unless:isTargetDiperban,true|required|date|after_or_equal:tmtAwal',
        ], [
            'employeeNik.required' => 'Pilih karyawan yang akan dimutasi.',
            'targetJabatan.required' => 'Jabatan tujuan wajib diisi.',
            'targetUnitKerja.required' => 'Unit kerja tujuan wajib diisi.',
            'tmtAwal.required' => 'Tanggal TMT Awal wajib diisi.',
            'nomorSk.required' => 'Nomor SK wajib diisi untuk Audit Trail.',
            'tanggalSk.required' => 'Tanggal Penerbitan SK wajib diisi.',
            'catatan.required' => 'Catatan dasar pertimbangan wajib diisi.',
            'tanggalBerakhirSanksi.required' => 'Tanggal kedaluwarsa sanksi wajib diisi untuk jabatan Diperban.',
            'tanggalBerakhirSanksi.after_or_equal' => 'Tanggal kedaluwarsa sanksi tidak boleh sebelum TMT awal.',
        ]);

        // If target is occupied, require explicit swap confirmation
        if (
            isset($this->targetAnalysis['type']) &&
            $this->targetAnalysis['type'] === 'occupied' &&
            ! $this->swapConfirmed
        ) {
            $this->addError('swapConfirmed', 'Konfirmasi tukar jabatan harus dicentang.');

            return;
        }

        try {
            $result = $service->processMutasi(
                employeeNik: $this->employeeNik,
                targetJabatan: $this->targetJabatan,
                targetUnitKerja: $this->targetUnitKerja,
                tmtAwal: $this->tmtAwal,
                jenisMutasi: $this->jenisMutasi,
                nomorSk: $this->nomorSk ?: null,
                tanggalSk: $this->tanggalSk ?: null,
                catatan: $this->catatan ?: null,
                tanggalBerakhirSanksi: $this->isTargetDiperban ? $this->tanggalBerakhirSanksi : null,
                statusJabatan: $this->statusJabatan,
                tmtAkhirEstimasi: in_array($this->statusJabatan, ['Pj', 'Plt']) && ! empty($this->tmtAkhirEstimasi) ? $this->tmtAkhirEstimasi : null,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->addError('employeeNik', $e->getMessage());

            return;
        }

        $msg = match ($result['type']) {
            'swap' => "Sukses! {$result['employee']} dan {$result['displaced']} berhasil bertukar jabatan.",
            'transfer' => "Sukses! {$result['employee']} berhasil dimutasi ke {$result['to_jabatan']} di {$result['to_unit']}.",
            default => 'Mutasi berhasil diproses.',
        };

        session()->flash('mutasi_success', $msg);
        $this->redirect(route('admin.mutasi.index', ['tab' => 'history']), navigate: false);
    }

    // ── Render ─────────────────────────────────────────────────────────────

    public function render(MutasiService $service)
    {
        $empQuery = Employee::orderBy('nama');
        if (! empty($this->sourceUnitFilter)) {
            $empQuery->where('unit_kerja', $this->sourceUnitFilter);
        }
        $employees = $empQuery->get(['nik', 'nama', 'jabatan', 'unit_kerja']);

        $jabatanList = $this->getFilteredJabatanList($service);
        $unitList = $service->getKnownUnits();
        $mutasiTypes = JobHistory::MUTASI_TYPES;

        $activeUnits = Unit::where('is_active', true)->orderBy('urutan')->orderBy('nama')->get();
        if ($activeUnits->isNotEmpty()) {
            $groupedUnits = $activeUnits->groupBy('wilayah')
                ->map(fn ($units) => $units->pluck('nama')->toArray())
                ->toArray();
        } else {
            $groupedUnits = Employee::GROUPED_UNITS;
        }

        return view('livewire.admin.mutasi-process', compact(
            'employees', 'jabatanList', 'unitList', 'groupedUnits', 'mutasiTypes'
        ));
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function getFilteredJabatanList(MutasiService $service)
    {
        if (! $this->employeeNik || ! $this->jenisMutasi || ! $this->targetUnitKerja) {
            return collect(); // Return empty if no employee or unit selected
        }

        $emp = Employee::where('nik', $this->employeeNik)->first();
        if (! $emp) {
            return collect();
        }

        $positionsArray = app(MutasiService::class)
            ->getAvailableTargetPositions($emp, $this->targetUnitKerja, $this->jenisMutasi);

        return collect($positionsArray);
    }

    private function resolveEmployee(string $nik): ?array
    {
        if (! $nik) {
            return null;
        }

        $emp = Employee::with('currentJob')->where('nik', $nik)->first();
        if (! $emp) {
            return null;
        }

        // Get RM Level
        $rmLevel = app(ManPowerPlanningService::class)->determineRmLevel($emp);

        return [
            'nik' => $emp->nik,
            'nama' => $emp->nama,
            'jabatan' => $emp->currentJob?->jabatan ?? $emp->jabatan,
            'unit_kerja' => $emp->currentJob?->unit_kerja ?? $emp->unit_kerja,
            'level' => $emp->currentJob?->level ?? $emp->level,
            'golongan' => $emp->golongan,
            'has_job' => (bool) $emp->currentJob,
            'rm_level' => $rmLevel,
        ];
    }

    private function runAnalysis(): void
    {
        if (! $this->targetJabatan || ! $this->targetUnitKerja) {
            $this->targetAnalysis = null;

            return;
        }

        // Same-position guard
        if (
            $this->employeeDetails &&
            $this->employeeDetails['jabatan'] === $this->targetJabatan &&
            $this->employeeDetails['unit_kerja'] === $this->targetUnitKerja
        ) {
            $this->targetAnalysis = ['type' => 'same'];

            return;
        }

        $analysis = app(MutasiService::class)->analyzeTarget(
            $this->targetJabatan,
            $this->targetUnitKerja
        );

        $this->targetAnalysis = [
            'type' => $analysis['type'],
            'occupant' => $analysis['occupant'] ? [
                'nik' => $analysis['occupant']->nik,
                'nama' => $analysis['occupant']->nama,
                'jabatan' => $analysis['occupant']->jabatan,
                'unit_kerja' => $analysis['occupant']->unit_kerja,
                'golongan' => $analysis['occupant']->golongan,
            ] : null,
        ];

        $targetPos = Position::getAllCached()->get($this->targetJabatan);
        $this->isTargetDiperban = $targetPos ? (bool) $targetPos->is_diperban : false;
        $this->isTargetOverflow = $targetPos ? (bool) $targetPos->is_overflow : false;
    }
}
