<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\MasterAuditLog;
use Illuminate\Support\Facades\DB;

class EvaluationService
{
    /** Bobot tiap kriteria pada nilai tertimbang (jumlah 1,0). Satu-satunya sumber bobot: form dan rumus memakainya. */
    public const BOBOT = [
        'nilai_kepemimpinan' => 0.4,
        'nilai_perilaku_budaya' => 0.3,
        'nilai_pengalaman_teknis' => 0.2,
        'nilai_kematangan_pribadi' => 0.1,
    ];

    /** Kolom turunan yang tidak dicatat sebagai perubahan isian pengguna. */
    private const DERIVED_COLUMNS = ['nilai_tertimbang'];

    /**
     * Menghitung nilai tertimbang berdasarkan bobot yang ditentukan
     * - Nilai Kepemimpinan: 40%
     * - Nilai Perilaku Budaya: 30%
     * - Nilai Pengalaman Teknis: 20%
     * - Nilai Kematangan Pribadi: 10%
     */
    public function calculateNilaiTertimbang(float $nk, float $npb, float $npt, float $nkp): float
    {
        return ($nk * self::BOBOT['nilai_kepemimpinan'])
            + ($npb * self::BOBOT['nilai_perilaku_budaya'])
            + ($npt * self::BOBOT['nilai_pengalaman_teknis'])
            + ($nkp * self::BOBOT['nilai_kematangan_pribadi']);
    }

    /**
     * Bobot satu kriteria dalam persen untuk ditampilkan pada formulir (mis. 40).
     */
    public static function bobotPersen(string $kriteria): int
    {
        return (int) round((self::BOBOT[$kriteria] ?? 0) * 100);
    }

    /**
     * Memproses dan menyimpan data Evaluation baru.
     * Penyimpanan dan jejak audit ditulis dalam satu transaksi.
     */
    public function processStore(array $data): Evaluation
    {
        $data['nilai_tertimbang'] = $this->weightedFrom($data);

        return DB::transaction(function () use ($data): Evaluation {
            $evaluation = Evaluation::create($data);

            $this->audit('evaluation.created', $evaluation, null, ['kolom_diubah' => $this->filledLabels($evaluation)]);

            return $evaluation;
        });
    }

    /**
     * Memproses dan mengupdate data Evaluation.
     * Skor 9-box bersifat rahasia (UU PDP): jejak audit hanya memuat nama kolom yang berubah, bukan nilainya.
     */
    public function processUpdate(Evaluation $evaluation, array $data): bool
    {
        $data['nilai_tertimbang'] = $this->weightedFrom($data);

        return DB::transaction(function () use ($evaluation, $data): bool {
            $evaluation->fill($data);
            $changed = $this->changedLabels($evaluation);
            $saved = $evaluation->save();

            if ($changed !== []) {
                $this->audit('evaluation.updated', $evaluation, null, ['kolom_diubah' => $changed]);
            }

            return $saved;
        });
    }

    /**
     * Menghapus penilaian dan mencatat penghapusan pada riwayat perubahan dalam satu transaksi.
     */
    public function processDestroy(Evaluation $evaluation, string $reason): void
    {
        DB::transaction(function () use ($evaluation, $reason): void {
            $this->audit('evaluation.deleted', $evaluation, null, null, $reason);

            $evaluation->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function weightedFrom(array $data): float
    {
        return $this->calculateNilaiTertimbang(
            (float) ($data['nilai_kepemimpinan'] ?? 0.0),
            (float) ($data['nilai_perilaku_budaya'] ?? 0.0),
            (float) ($data['nilai_pengalaman_teknis'] ?? 0.0),
            (float) ($data['nilai_kematangan_pribadi'] ?? 0.0),
        );
    }

    /**
     * @return list<string>
     */
    private function changedLabels(Evaluation $evaluation): array
    {
        return $this->labelsFor(array_keys($evaluation->getDirty()));
    }

    /**
     * @return list<string>
     */
    private function filledLabels(Evaluation $evaluation): array
    {
        $filled = array_keys(array_filter(
            $evaluation->getAttributes(),
            fn (mixed $value, string $key): bool => $value !== null && $value !== '' && ! in_array($key, ['id', 'employee_nik', 'created_at', 'updated_at'], true),
            ARRAY_FILTER_USE_BOTH,
        ));

        return $this->labelsFor($filled);
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function labelsFor(array $columns): array
    {
        return array_values(array_map(
            fn (string $column): string => MasterAuditLog::FIELD_LABELS[$column] ?? $column,
            array_values(array_diff($columns, self::DERIVED_COLUMNS, ['updated_at'])),
        ));
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(string $action, Evaluation $evaluation, ?array $old, ?array $new, ?string $reason = null): void
    {
        $evaluation->loadMissing('employee:nik,nama');

        MasterAuditLog::record(
            auth()->user(),
            $action,
            'evaluation',
            $evaluation->id,
            'Penilaian '.$evaluation->employee_nik.' - '.($evaluation->employee?->nama ?? '-'),
            $old,
            $new,
            $reason,
        );
    }
}
