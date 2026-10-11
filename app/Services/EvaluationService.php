<?php

namespace App\Services;

use App\Models\Evaluation;

class EvaluationService
{
    /**
     * Menghitung nilai tertimbang berdasarkan bobot yang ditentukan
     * - Nilai Kepemimpinan: 40%
     * - Nilai Perilaku Budaya: 30%
     * - Nilai Pengalaman Teknis: 20%
     * - Nilai Kematangan Pribadi: 10%
     */
    public function calculateNilaiTertimbang(float $nk, float $npb, float $npt, float $nkp): float
    {
        return ($nk * 0.4) + ($npb * 0.3) + ($npt * 0.2) + ($nkp * 0.1);
    }

    /**
     * Memproses dan menyimpan data Evaluation baru
     */
    public function processStore(array $data): Evaluation
    {
        $nk = (float) ($data['nilai_kepemimpinan'] ?? 0.0);
        $npb = (float) ($data['nilai_perilaku_budaya'] ?? 0.0);
        $npt = (float) ($data['nilai_pengalaman_teknis'] ?? 0.0);
        $nkp = (float) ($data['nilai_kematangan_pribadi'] ?? 0.0);

        $data['nilai_tertimbang'] = $this->calculateNilaiTertimbang($nk, $npb, $npt, $nkp);

        return Evaluation::create($data);
    }

    /**
     * Memproses dan mengupdate data Evaluation
     */
    public function processUpdate(Evaluation $evaluation, array $data): bool
    {
        $nk = (float) ($data['nilai_kepemimpinan'] ?? 0.0);
        $npb = (float) ($data['nilai_perilaku_budaya'] ?? 0.0);
        $npt = (float) ($data['nilai_pengalaman_teknis'] ?? 0.0);
        $nkp = (float) ($data['nilai_kematangan_pribadi'] ?? 0.0);

        $data['nilai_tertimbang'] = $this->calculateNilaiTertimbang($nk, $npb, $npt, $nkp);

        return $evaluation->update($data);
    }
}
