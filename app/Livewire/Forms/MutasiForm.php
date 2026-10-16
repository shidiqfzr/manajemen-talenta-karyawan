<?php

namespace App\Livewire\Forms;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class MutasiForm extends Form
{
    // ── Mutation Type & Legal ──────────────────────────────────────────────
    public string $jenisMutasi = 'PROMOSI';

    public string $statusJabatan = 'Definitif';

    public string $tmtAwal = '';

    public string $nomorSk = '';

    public string $tanggalSk = '';

    public $fileSk = null;

    public string $catatan = '';

    public string $tmtAkhirEstimasi = '';

    // ── Penyesuaian Profil Pegawai (DOMAIN.md §4 & §5.2.3) ────────────────
    public ?string $newGolongan = '';

    public ?string $newJobGrade = '';

    // ── Dispensasi SSOT ───────────────────────────────────────────────────
    public bool $dispensasiTenure = false;

    public string $alasanDispensasiTenure = '';

    public bool $dispensasiPromosi = false;

    public string $alasanDispensasiPromosi = '';

    // ── Employee (source) ──────────────────────────────────────────────────
    public string $employeeNik = '';

    public string $sourceUnitFilter = '';

    // ── Target position ────────────────────────────────────────────────────
    public string $targetJabatan = '';

    public string $targetUnitKerja = '';

    // ── Confirmation & Diperban ────────────────────────────────────────────
    public bool $swapConfirmed = false;

    public bool $isTargetDiperban = false;

    public bool $isTargetOverflow = false;

    public string $tanggalBerakhirSanksi = '';

    public function initDefaults(): void
    {
        $this->tmtAwal = now()->toDateString();
        $this->tanggalSk = now()->toDateString();
    }

    public function resetTarget(bool $resetUnit = true): void
    {
        $this->targetJabatan = '';
        if ($resetUnit) {
            $this->targetUnitKerja = '';
        }
        $this->swapConfirmed = false;
        $this->isTargetDiperban = false;
        $this->isTargetOverflow = false;
        $this->tanggalBerakhirSanksi = '';
        $this->dispensasiTenure = false;
        $this->alasanDispensasiTenure = '';
        $this->dispensasiPromosi = false;
        $this->alasanDispensasiPromosi = '';
    }

    public function resetEmployee(): void
    {
        $this->employeeNik = '';
        $this->newGolongan = '';
        $this->newJobGrade = '';
        $this->resetTarget(resetUnit: true);
    }

    /**
     * Validate the mutasi form.
     */
    public function validateForm(
        ?array $tenureAnalysis,
        ?array $promotionAnalysis,
        ?array $targetAnalysis,
        ?array $employeeDetails,
        bool $isSwapAllowed,
        ?string $swapIncompatibilityMessage
    ): bool {
        $rules = [
            'jenisMutasi' => 'required|string',
            'statusJabatan' => 'required|in:Definitif,Pj,Plt',
            'employeeNik' => 'required|exists:employees,nik',
            'targetJabatan' => 'required|string',
            'targetUnitKerja' => 'required|string',
            'tmtAwal' => 'required|date',
            'nomorSk' => 'required|string|max:100',
            'tanggalSk' => 'required|date',
            'fileSk' => 'nullable|file|mimes:pdf|max:5120',
            'catatan' => 'required|string|max:500',
            'tmtAkhirEstimasi' => 'nullable|date|after_or_equal:tmtAwal',
            'tanggalBerakhirSanksi' => 'exclude_unless:isTargetDiperban,true|required|date|after_or_equal:tmtAwal',
            'newGolongan' => 'nullable|string|max:20',
            'newJobGrade' => 'nullable|string|max:10',
        ];

        $messages = [
            'employeeNik.required' => 'Pilih karyawan yang akan dimutasi.',
            'targetJabatan.required' => 'Jabatan tujuan wajib diisi.',
            'targetUnitKerja.required' => 'Unit kerja tujuan wajib diisi.',
            'tmtAwal.required' => 'Tanggal TMT Awal wajib diisi.',
            'nomorSk.required' => 'Nomor SK wajib diisi untuk Audit Trail.',
            'tanggalSk.required' => 'Tanggal Penerbitan SK wajib diisi.',
            'fileSk.mimes' => 'Berkas SK harus berupa dokumen PDF.',
            'fileSk.max' => 'Ukuran berkas SK maksimal 5 MB.',
            'catatan.required' => 'Catatan dasar pertimbangan wajib diisi.',
            'tanggalBerakhirSanksi.required' => 'Tanggal kedaluwarsa sanksi wajib diisi untuk jabatan Diperban.',
            'tanggalBerakhirSanksi.after_or_equal' => 'Tanggal kedaluwarsa sanksi tidak boleh sebelum TMT awal.',
        ];

        // Tenure Guard validation
        if ($tenureAnalysis && ! $tenureAnalysis['is_compliant']) {
            $rules['dispensasiTenure'] = 'accepted';
            $rules['alasanDispensasiTenure'] = 'required|string|min:5|max:255';
            $messages['dispensasiTenure.accepted'] = 'Masa dinas di unit belum mencapai 2 tahun. Wajib mencentang persetujuan Dispensasi Khusus Direksi.';
            $messages['alasanDispensasiTenure.required'] = 'Alasan pertimbangan dispensasi masa dinas unit wajib diisi.';
        }

        // Promotion Eligibility validation
        if ($promotionAnalysis && ! $promotionAnalysis['is_eligible']) {
            $rules['dispensasiPromosi'] = 'accepted';
            $rules['alasanDispensasiPromosi'] = 'required|string|min:5|max:255';
            $messages['dispensasiPromosi.accepted'] = 'Kriteria formal promosi belum terpenuhi. Wajib mencentang persetujuan Dispensasi Promosi.';
            $messages['alasanDispensasiPromosi.required'] = 'Alasan pertimbangan dispensasi promosi wajib diisi.';
        }

        try {
            $this->validate($rules, $messages);
        } catch (ValidationException $e) {
            if (isset($this->component)) {
                foreach ($e->errors() as $field => $errorMessages) {
                    $cleanField = str_starts_with($field, $this->propertyName.'.')
                        ? substr($field, strlen($this->propertyName) + 1)
                        : $field;
                    foreach ($errorMessages as $msg) {
                        $this->component->addError($cleanField, $msg);
                    }
                }
            }
            throw $e;
        }

        // Domain Rule: Cek Batas Usia Pensiun
        if ($this->employeeNik) {
            $emp = Employee::where('nik', $this->employeeNik)->first();
            if ($emp && $emp->tanggal_pensiun && Carbon::parse($emp->tanggal_pensiun)->lt(now()->startOfMonth())) {
                $msg = 'Karyawan telah melewati batas usia pensiun dan tidak dapat dimutasi.';
                $this->addError('employeeNik', $msg);
                if (isset($this->component)) {
                    $this->component->addError('employeeNik', $msg);
                }

                return false;
            }
        }

        // Domain Validation: Rotasi vs Mutasi Unit (DOMAIN.md §5.4)
        if ($employeeDetails) {
            if ($this->jenisMutasi === 'ROTASI' && $this->targetUnitKerja !== $employeeDetails['unit_kerja']) {
                $msg = 'Rotasi & Alih Tugas hanya berlaku di dalam unit kerja yang sama ('.$employeeDetails['unit_kerja'].').';
                $this->addError('targetUnitKerja', $msg);
                if (isset($this->component)) {
                    $this->component->addError('targetUnitKerja', $msg);
                }

                return false;
            }
            if ($this->jenisMutasi === 'MUTASI_UNIT' && $this->targetUnitKerja === $employeeDetails['unit_kerja']) {
                $msg = 'Mutasi Unit wajib menuju unit kerja yang berbeda (gunakan jenis Rotasi jika tetap di unit yang sama).';
                $this->addError('targetUnitKerja', $msg);
                if (isset($this->component)) {
                    $this->component->addError('targetUnitKerja', $msg);
                }

                return false;
            }
        }

        // Lateral Swap Validation (DOMAIN.md §5.2.2 & Enterprise Governance)
        if (
            isset($targetAnalysis['type']) &&
            $targetAnalysis['type'] === 'occupied'
        ) {
            if (! $isSwapAllowed) {
                $msg = $swapIncompatibilityMessage ?? 'Tukar jabatan tidak diperbolehkan antar eselon yang berbeda.';
                $this->addError('swapConfirmed', $msg);
                if (isset($this->component)) {
                    $this->component->addError('swapConfirmed', $msg);
                }

                return false;
            }

            if (! $this->swapConfirmed) {
                $msg = 'Konfirmasi tukar jabatan harus dicentang.';
                $this->addError('swapConfirmed', $msg);
                if (isset($this->component)) {
                    $this->component->addError('swapConfirmed', $msg);
                }

                return false;
            }
        }

        return true;
    }
}
