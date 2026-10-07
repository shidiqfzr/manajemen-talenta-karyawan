<?php

namespace App\Livewire\Admin;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Services\ManPowerPlanningService;
use App\Services\MutasiService;
use Livewire\Component;

class MutasiProcess extends Component
{
    // ── Mutation Type & Legal ──────────────────────────────────────────────
    public string $jenisMutasi = 'PROMOSI';
    public string $tmtAwal = '';
    public string $nomorSk = '';
    public string $tanggalSk = '';
    public string $catatan = '';

    // ── Employee (source) ──────────────────────────────────────────────────
    public string $employeeNik = '';
    public ?array $employeeDetails = null;

    // ── Target position ────────────────────────────────────────────────────
    public string $targetJabatan = '';
    public string $targetUnitKerja = '';

    // ── Live impact analysis ───────────────────────────────────────────────
    /** null = not yet analysed, array = analysis result */
    public ?array $targetAnalysis = null;

    /** Explicit confirmation required when target is occupied (swap) */
    public bool $swapConfirmed = false;


    public function mount(?string $jabatan = null, ?string $unit = null): void
    {
        $this->tmtAwal = now()->toDateString();
        $this->tanggalSk = now()->toDateString();

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
        $this->resetTarget();
    }

    public function updatedEmployeeNik(string $value): void
    {
        $this->employeeDetails = $this->resolveEmployee($value);
        $this->resetTarget();
    }

    public function updatedTargetJabatan(): void
    {
        $this->swapConfirmed = false;
        $this->runAnalysis();
    }

    public function updatedTargetUnitKerja(): void
    {
        $this->swapConfirmed = false;
        $this->runAnalysis();
    }

    private function resetTarget(): void
    {
        $this->targetJabatan = '';
        $this->targetUnitKerja = '';
        $this->targetAnalysis = null;
        $this->swapConfirmed = false;
    }

    // ── Submit ─────────────────────────────────────────────────────────────

    public function submitMutasi(MutasiService $service): void
    {
        $this->validate([
            'jenisMutasi' => 'required|string',
            'employeeNik' => 'required|exists:employees,nik',
            'targetJabatan' => 'required|string',
            'targetUnitKerja' => 'required|string',
            'tmtAwal' => 'required|date',
            'nomorSk' => 'nullable|string|max:100',
            'tanggalSk' => 'nullable|date',
            'catatan' => 'nullable|string|max:500',
        ], [
            'employeeNik.required' => 'Pilih karyawan yang akan dimutasi.',
            'targetJabatan.required' => 'Jabatan tujuan wajib diisi.',
            'targetUnitKerja.required' => 'Unit kerja tujuan wajib diisi.',
            'tmtAwal.required' => 'Tanggal TMT Awal wajib diisi.',
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
        $employees = Employee::orderBy('nama')->get(['nik', 'nama', 'jabatan', 'unit_kerja']);
        
        $jabatanList = $this->getFilteredJabatanList($service);
        $unitList = $service->getKnownUnits();
        $mutasiTypes = JobHistory::MUTASI_TYPES;

        return view('livewire.admin.mutasi-process', compact(
            'employees', 'jabatanList', 'unitList', 'mutasiTypes'
        ));
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function getFilteredJabatanList(MutasiService $service)
    {
        $allJabatan = $service->getKnownJabatan();
        
        if (!$this->employeeNik || !$this->jenisMutasi) {
            return collect(); // Return empty if no employee selected
        }

        $emp = Employee::where('nik', $this->employeeNik)->first();
        if (!$emp) return collect();

        $empRmLevel = app(ManPowerPlanningService::class)->determineRmLevel($emp);
        $empRmNumber = (int) str_replace('RM-', '', $empRmLevel); // 1, 2, 3, or 4

        $filtered = collect();
        $positions = Position::all()->keyBy('nama'); // To look up rm_level

        foreach ($allJabatan as $j) {
            $jRmNumber = null;
            if (isset($positions[$j]) && $positions[$j]->rm_level) {
                $jRmNumber = (int) str_replace('RM-', '', $positions[$j]->rm_level);
            } else {
                // Determine by string matching (logic from ManPowerPlanningService)
                $jabatanLower = strtolower($j);
                if (str_contains($jabatanLower, 'manajer') || str_contains($jabatanLower, 'general manager') || str_contains($jabatanLower, 'kepala bagian') || str_contains($jabatanLower, 'kabag') || str_contains($jabatanLower, 'koordinator spi') || str_contains($jabatanLower, 'senior manager') || str_contains($jabatanLower, 'pimpinan')) {
                    $jRmNumber = 1;
                } elseif (str_contains($jabatanLower, 'askep') || str_contains($jabatanLower, 'asisten kepala') || str_contains($jabatanLower, 'kasubag') || str_contains($jabatanLower, 'kepala sub') || str_contains($jabatanLower, 'masinis kepala')) {
                    $jRmNumber = 2;
                } elseif (str_contains($jabatanLower, 'asisten')) {
                    $jRmNumber = 3;
                } else {
                    $jRmNumber = 4;
                }
            }

            // Note: RM-1 is highest rank (numerically lower).
            if ($this->jenisMutasi === 'PROMOSI') {
                if ($jRmNumber < $empRmNumber) $filtered->push($j);
            } elseif ($this->jenisMutasi === 'DEMOSI') {
                if ($jRmNumber > $empRmNumber) $filtered->push($j);
            } elseif (in_array($this->jenisMutasi, ['ROTASI', 'MUTASI_UNIT', 'ALIH_TUGAS'])) {
                // Same level
                if ($jRmNumber === $empRmNumber) $filtered->push($j);
            } else {
                // LAINNYA / PENUGASAN -> no filter
                $filtered->push($j);
            }
        }

        return $filtered->sort()->values();
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
            'type' => $analysis['vacant'] ? 'vacant' : 'occupied',
            'occupant' => $analysis['occupant'] ? [
                'nik' => $analysis['occupant']->nik,
                'nama' => $analysis['occupant']->nama,
                'jabatan' => $analysis['occupant']->jabatan,
                'unit_kerja' => $analysis['occupant']->unit_kerja,
                'golongan' => $analysis['occupant']->golongan,
            ] : null,
        ];
    }
}
