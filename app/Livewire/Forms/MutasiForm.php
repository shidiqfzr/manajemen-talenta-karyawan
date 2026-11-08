<?php

namespace App\Livewire\Forms;

use App\Models\JobHistory;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class MutasiForm extends Form
{
    // ── Mutation Type & Legal ──────────────────────────────────────────────
    public string $jenisMutasi = 'MUTASI_UNIT';

    public string $statusJabatan = 'Definitif';

    public string $tmtAwal = '';

    public string $nomorSk = '';

    public string $tanggalSk = '';

    public $fileSk = null;

    public string $catatan = '';

    public string $tmtAkhirEstimasi = '';

    // ── Penyesuaian Profil Karyawan (DOMAIN.md §4 & §5.2.3) ────────────────
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
     * Validate the complete mutasi form.
     */
    public function validateForm(
        ?array $tenureAnalysis,
        ?array $promotionAnalysis,
        ?array $targetAnalysis,
        ?array $employeeDetails,
        bool $isSwapAllowed,
        ?string $swapIncompatibilityMessage
    ): bool {
        // 1. Domain Rule: Cek Formasi Dipesan (Reserved Position Guard)
        if ($targetAnalysis && ($targetAnalysis['is_reserved'] ?? false)) {
            $reservedName = $targetAnalysis['reserved_occupant']['nama'] ?? 'karyawan lain';
            $reservedTmt = $targetAnalysis['reserved_occupant']['tmt_awal'] ?? '-';
            $msg = "Formasi ini telah dipesan oleh mutasi terjadwal atas nama {$reservedName} (TMT Efektif: {$reservedTmt}). Pilih formasi lain yang tersedia.";
            $this->addFormAndComponentError('targetJabatan', $msg);

            throw ValidationException::withMessages(['targetJabatan' => [$msg]]);
        }

        // 2. Domain Rule: Cek Batas Usia Pensiun (flag derived server-side by MutasiService::getTalentProfile)
        if ($employeeDetails['is_retired'] ?? false) {
            $msg = 'Karyawan telah melewati batas usia pensiun dan tidak dapat dimutasi.';
            $this->addFormAndComponentError('employeeNik', $msg);

            throw ValidationException::withMessages(['employeeNik' => [$msg]]);
        }

        // 3. Domain Validation: Rotasi vs Mutasi Unit (DOMAIN.md §5.4)
        if ($employeeDetails) {
            if ($this->jenisMutasi === 'ROTASI' && $this->targetUnitKerja !== $employeeDetails['unit_kerja']) {
                $msg = 'Rotasi & Alih Tugas hanya berlaku di dalam unit kerja yang sama ('.$employeeDetails['unit_kerja'].').';
                $this->addFormAndComponentError('targetUnitKerja', $msg);

                throw ValidationException::withMessages(['targetUnitKerja' => [$msg]]);
            }
            if ($this->jenisMutasi === 'MUTASI_UNIT' && $this->targetUnitKerja === $employeeDetails['unit_kerja']) {
                $msg = 'Mutasi Unit wajib menuju unit kerja yang berbeda (gunakan jenis Rotasi jika tetap di unit yang sama).';
                $this->addFormAndComponentError('targetUnitKerja', $msg);

                throw ValidationException::withMessages(['targetUnitKerja' => [$msg]]);
            }
        }

        // 4. Lateral Swap Validation (DOMAIN.md §5.2.2 & Enterprise Governance)
        if (
            isset($targetAnalysis['type']) &&
            $targetAnalysis['type'] === 'occupied'
        ) {
            if ($this->statusJabatan !== 'Definitif') {
                $msg = "Penugasan sementara ({$this->statusJabatan}) hanya dapat ditempatkan pada formasi kosong. Pilih status Definitif untuk tukar jabatan atau pilih formasi lain.";
                $this->addFormAndComponentError('statusJabatan', $msg);

                throw ValidationException::withMessages(['statusJabatan' => [$msg]]);
            }

            if (! $isSwapAllowed) {
                $msg = $swapIncompatibilityMessage ?? 'Tukar jabatan tidak diperbolehkan antar eselon yang berbeda.';
                $this->addFormAndComponentError('swapConfirmed', $msg);

                throw ValidationException::withMessages(['swapConfirmed' => [$msg]]);
            }

            if (! $this->swapConfirmed) {
                $msg = 'Konfirmasi tukar jabatan harus dicentang.';
                $this->addFormAndComponentError('swapConfirmed', $msg);

                throw ValidationException::withMessages(['swapConfirmed' => [$msg]]);
            }
        }

        // 5. Standard Form Field Rules
        $rules = [
            'jenisMutasi' => 'required|string|in:'.implode(',', JobHistory::MUTASI_TYPES),
            'statusJabatan' => 'required|in:'.implode(',', JobHistory::STATUS_JABATAN),
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
            'jenisMutasi.in' => 'Jenis mutasi tidak dikenal dalam Pedoman Tata Kelola SDM.',
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
            $this->syncComponentErrors($e);
            throw $e;
        }

        return true;
    }

    /**
     * Synchronize ValidationException error messages to both Form and Component.
     */
    private function syncComponentErrors(ValidationException $e): void
    {
        if (isset($this->component)) {
            foreach ($e->errors() as $field => $errorMessages) {
                $cleanField = str_starts_with($field, $this->propertyName.'.')
                    ? substr($field, strlen($this->propertyName) + 1)
                    : $field;
                foreach ($errorMessages as $msg) {
                    $this->component->addError($cleanField, $msg);
                    $this->component->addError($this->propertyName.'.'.$cleanField, $msg);
                }
            }
        }
    }

    /**
     * Add error to both Form object and host Component.
     */
    private function addFormAndComponentError(string $field, string $message): void
    {
        $this->addError($field, $message);
        if (isset($this->component)) {
            $this->component->addError($field, $message);
            $this->component->addError($this->propertyName.'.'.$field, $message);
        }
    }
}
