<?php

namespace App\Services\Structure;

use App\Models\JobHistory;
use App\Models\StructureDecree;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Struktur organisasi menurut tanggal (DOMAIN.md §3.7).
 *
 * Versi struktur pada tanggal D adalah snapshot SK Diterapkan terbaru yang tanggal berlakunya tidak setelah D. Pemangku
 * jabatan dibaca dari job_histories (riwayat tidak pernah ditulis ulang). Tanggal sebelum SK pertama tidak direkonstruksi:
 * hasilnya null, bukan struktur karangan.
 */
class StructureAsOfService
{
    public function __construct(private StructureReader $reader, private StructureDiffCalculator $calculator) {}

    public function decreeFor(CarbonInterface $date): ?StructureDecree
    {
        return StructureDecree::query()
            ->where('status', StructureDecree::STATUS_APPLIED)
            ->whereNotNull('snapshot')
            ->whereDate('effective_date', '<=', $date->toDateString())
            ->orderByDesc('effective_date')
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{decree: StructureDecree, rows: list<array<string, mixed>>, outside_structure: int}|null
     */
    public function report(CarbonInterface $date): ?array
    {
        $decree = $this->decreeFor($date);
        if ($decree === null) {
            return null;
        }

        $holders = $this->holders($date);
        $rows = [];

        foreach ($decree->snapshot['units'] ?? [] as $unit => $data) {
            foreach ($data['seats'] ?? [] as $jabatan => $seat) {
                $placed = $holders[$unit][$jabatan] ?? [];
                unset($holders[$unit][$jabatan]);

                $rows[] = [
                    'unit' => (string) $unit,
                    'wilayah' => $data['wilayah'] ?? null,
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'jabatan' => (string) $jabatan,
                    'rm_level' => $seat['rm_level'] ?? null,
                    'level' => $seat['level'] ?? null,
                    'bidang' => $seat['bidang'] ?? null,
                    'kuota' => (int) ($seat['kuota'] ?? 0),
                    'holder_count' => count($placed),
                    'holders' => $placed,
                ];
            }
        }

        return [
            'decree' => $decree,
            'rows' => $rows,
            'outside_structure' => array_sum(array_map(fn (array $byJabatan): int => array_sum(array_map('count', $byJabatan)), $holders)),
        ];
    }

    /**
     * Selisih dari versi pada tanggal itu menuju struktur sekarang; null bila belum ada versi.
     */
    public function compareWithCurrent(CarbonInterface $date): ?StructureDiff
    {
        $decree = $this->decreeFor($date);

        return $decree === null ? null : $this->calculator->calculate($this->reader->current(), $decree->snapshot);
    }

    /**
     * Pemangku yang menjabat pada tanggal itu, dikelompokkan per unit lalu jabatan. Baris terjadwal yang belum berlaku
     * (is_applied = false) dan baris terhapus lunak tidak dihitung. NIK tidak pernah keluar utuh (UU PDP).
     *
     * @return array<string, array<string, list<array{nama: string, nik_masked: string, status_penugasan: ?string}>>>
     */
    private function holders(CarbonInterface $date): array
    {
        $day = $date->toDateString();

        $rows = JobHistory::query()
            ->join('employees', 'employees.nik', '=', 'job_histories.employee_nik')
            ->where('job_histories.is_applied', true)
            ->whereDate('job_histories.tmt_awal', '<=', $day)
            ->where(function ($query) use ($day): void {
                $query->whereNull('job_histories.tmt_akhir')->orWhereDate('job_histories.tmt_akhir', '>=', $day);
            })
            ->orderBy('employees.nama')
            ->get(['job_histories.unit_kerja', 'job_histories.jabatan', 'job_histories.status_penugasan', 'job_histories.employee_nik', DB::raw('employees.nama as nama')]);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->unit_kerja][$row->jabatan][] = [
                'nama' => (string) $row->nama,
                'nik_masked' => self::maskNik((string) $row->employee_nik),
                'status_penugasan' => $row->status_penugasan,
            ];
        }

        return $grouped;
    }

    private static function maskNik(string $nik): string
    {
        return str_repeat('*', max(strlen($nik) - 3, 0)).substr($nik, -3);
    }
}
