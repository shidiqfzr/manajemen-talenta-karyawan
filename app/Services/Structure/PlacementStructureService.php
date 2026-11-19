<?php

namespace App\Services\Structure;

use App\Models\JobHistory;
use App\Models\StructureDecree;

/**
 * Mencocokkan penempatan (baris riwayat jabatan) dengan versi struktur yang berlaku pada tanggal mulainya (DOMAIN.md §3.7).
 * Dipakai kolom "Struktur yang Berlaku" pada Riwayat Karir karyawan. Hanya membaca: riwayat tidak pernah ditulis ulang.
 */
class PlacementStructureService
{
    /**
     * @param  iterable<JobHistory>  $histories
     * @return array<int, array{structure_sk: ?string, in_structure: ?bool}> dikunci menurut id riwayat. structure_sk dan
     *                                                                       in_structure null bila penempatan terjadi sebelum SK struktur pertama.
     */
    public function annotate(iterable $histories): array
    {
        $rows = collect($histories);
        if ($rows->isEmpty()) {
            return [];
        }

        $versions = StructureDecree::query()
            ->where('status', StructureDecree::STATUS_APPLIED)
            ->whereNotNull('snapshot')
            ->orderByDesc('effective_date')
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->get();

        return $rows->mapWithKeys(function (JobHistory $history) use ($versions): array {
            $start = $history->tmt_awal->toDateString();
            $version = $versions->first(fn (StructureDecree $decree): bool => $decree->effective_date->toDateString() <= $start);

            return [$history->id => [
                'structure_sk' => $version?->sk_number,
                'in_structure' => $version === null ? null : isset($version->snapshot['units'][$history->unit_kerja]['seats'][$history->jabatan]),
            ]];
        })->all();
    }
}
