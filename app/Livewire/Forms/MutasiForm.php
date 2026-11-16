<?php

namespace App\Livewire\Forms;

use App\Models\JobHistory;
use App\Services\Mutasi\DispensasiBasis;
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
    // One piece of evidence shown to the admin: document reference (alasanDispensasiDireksi) + approval date.
    // $dispensasiDireksi is derived from that evidence by validateForm() (never typed by the admin) and
    // is fanned out to the per-rule fields below.
    public bool $dispensasiDireksi = false;

    public string $alasanDispensasiDireksi = '';

    public string $tanggalDispensasiDireksi = '';

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
        $this->dispensasiDireksi = false;
        $this->alasanDispensasiDireksi = '';
        $this->tanggalDispensasiDireksi = '';
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
        ?array $targetAnalysis,
        ?array $employeeDetails,
        bool $isSwapAllowed,
        ?string $swapIncompatibilityMessage,
        ?array $promotionAnalysis = null
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

        // One Direksi dispensation covers every rule exception of this mutasi (masa dinas unit, kualifikasi promosi).
        // It is evidenced by one document reference + the approval date (DispensasiBasis); the approval flag is
        // derived from that evidence, fanned out to the per-rule flags that the service and audit trail use,
        // and never left true for a rule that is actually met.
        $needsTenureDispensation = (bool) ($tenureAnalysis
            && ! $tenureAnalysis['is_compliant']
            && ($tenureAnalysis['is_applicable'] ?? true));
        $needsPromotionDispensation = (bool) ($promotionAnalysis
            && ($promotionAnalysis['is_applicable'] ?? false)
            && ! ($promotionAnalysis['is_eligible'] ?? true));

        if ($needsTenureDispensation || $needsPromotionDispensation) {
            $rules['alasanDispensasiDireksi'] = 'required|string|min:'.DispensasiBasis::MIN_REFERENCE_LENGTH.'|max:'.DispensasiBasis::MAX_REFERENCE_LENGTH;
            $rules['tanggalDispensasiDireksi'] = 'required|date_format:Y-m-d|before_or_equal:today';
            $messages['alasanDispensasiDireksi.required'] = 'Nomor nota dinas / SK persetujuan Direksi wajib diisi karena terdapat pengecualian regulasi.';
            $messages['alasanDispensasiDireksi.min'] = 'Nomor nota dinas / SK persetujuan Direksi minimal '.DispensasiBasis::MIN_REFERENCE_LENGTH.' karakter.';
            $messages['alasanDispensasiDireksi.max'] = 'Nomor nota dinas / SK persetujuan Direksi maksimal '.DispensasiBasis::MAX_REFERENCE_LENGTH.' karakter.';
            $messages['tanggalDispensasiDireksi.required'] = 'Tanggal persetujuan Direksi wajib diisi.';
            $messages['tanggalDispensasiDireksi.date_format'] = 'Tanggal persetujuan Direksi tidak valid.';
            $messages['tanggalDispensasiDireksi.before_or_equal'] = 'Tanggal persetujuan Direksi tidak boleh melewati hari ini.';
        } else {
            $this->alasanDispensasiDireksi = '';
            $this->tanggalDispensasiDireksi = '';
        }

        $this->dispensasiDireksi = ($needsTenureDispensation || $needsPromotionDispensation)
            && DispensasiBasis::isComplete($this->alasanDispensasiDireksi, $this->tanggalDispensasiDireksi);
        $basis = $this->dispensasiDireksi
            ? DispensasiBasis::compose($this->alasanDispensasiDireksi, $this->tanggalDispensasiDireksi)
            : '';

        $this->dispensasiTenure = $needsTenureDispensation && $this->dispensasiDireksi;
        $this->alasanDispensasiTenure = $this->dispensasiTenure ? $basis : '';
        $this->dispensasiPromosi = $needsPromotionDispensation && $this->dispensasiDireksi;
        $this->alasanDispensasiPromosi = $this->dispensasiPromosi ? $basis : '';

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
