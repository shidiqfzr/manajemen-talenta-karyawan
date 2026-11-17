<?php

namespace App\Services\Structure;

use App\Models\JobHistory;
use App\Models\StructureDecree;
use App\Support\NikMasker;
use Carbon\CarbonImmutable;
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
     * Seluruh versi struktur (SK Diterapkan berisi snapshot), terbaru dahulu. `to` adalah hari terakhir versi itu
     * (sehari sebelum versi berikutnya berlaku), null bila versi tersebut masih berlaku. Hanya unit aktif yang dihitung.
     *
     * @return list<array{decree: StructureDecree, from: CarbonImmutable, to: ?CarbonImmutable, is_current: bool, units: int, seats: int, kuota: int}>
     */
    public function versions(?CarbonInterface $today = null): array
    {
        $today ??= now();

        $decrees = StructureDecree::query()
            ->where('status', StructureDecree::STATUS_APPLIED)
            ->whereNotNull('snapshot')
            ->orderByDesc('effective_date')
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->get();

        $currentId = $this->decreeFor($today)?->id;
        $versions = [];
        $nextStart = null;

        foreach ($decrees as $decree) {
            $units = array_filter($decree->snapshot['units'] ?? [], fn (array $unit): bool => (bool) ($unit['is_active'] ?? true));
            // Jumlah per unit lalu dijumlahkan: nama jabatan yang sama pada unit berbeda (mis. "Manajer") adalah formasi berbeda,
            // jadi tidak boleh digabung lewat flatMap (kunci string akan saling menimpa).
            $seatCount = 0;
            $kuota = 0;
            foreach ($units as $unit) {
                $seatCount += count($unit['seats'] ?? []);
                $kuota += array_sum(array_map(fn (array $seat): int => (int) ($seat['kuota'] ?? 0), $unit['seats'] ?? []));
            }

            $versions[] = [
                'decree' => $decree,
                'from' => $decree->effective_date->toImmutable(),
                'to' => $nextStart?->subDay(),
                'is_current' => $decree->id === $currentId,
                'units' => count($units),
                'seats' => $seatCount,
                'kuota' => $kuota,
            ];

            $nextStart = $decree->effective_date->toImmutable();
        }

        return $versions;
    }

    /**
     * @return array{decree: StructureDecree, rows: list<array<string, mixed>>, units: list<array<string, mixed>>, totals: array{units: int, seats: int, kuota: int, filled: int}, holder_total: int, outside_structure: int}|null
     */
    public function report(CarbonInterface $date): ?array
    {
        $decree = $this->decreeFor($date);
        if ($decree === null) {
            return null;
        }

        $holders = $this->holders($date);
        $holderTotal = array_sum(array_map(fn (array $byJabatan): int => array_sum(array_map('count', $byJabatan)), $holders));
        $rows = [];
        $units = [];

        foreach ($decree->snapshot['units'] ?? [] as $unit => $data) {
            foreach ($data['seats'] ?? [] as $jabatan => $seat) {
                $placed = $holders[$unit][$jabatan] ?? [];
                unset($holders[$unit][$jabatan]);

                $row = [
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
                $rows[] = $row;
                $units[$unit]['seats'][] = $row;
            }

            $units[$unit] = [
                'unit' => (string) $unit,
                'wilayah' => $data['wilayah'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'seats' => $units[$unit]['seats'] ?? [],
            ];
            $units[$unit]['seat_count'] = count($units[$unit]['seats']);
            $units[$unit]['kuota'] = array_sum(array_column($units[$unit]['seats'], 'kuota'));
            $units[$unit]['filled'] = array_sum(array_column($units[$unit]['seats'], 'holder_count'));
        }

        $units = array_values($units);
        $active = array_filter($units, fn (array $unit): bool => $unit['is_active']);

        return [
            'decree' => $decree,
            'rows' => $rows,
            'units' => $units,
            'totals' => [
                'units' => count($active),
                'seats' => array_sum(array_column($active, 'seat_count')),
                'kuota' => array_sum(array_column($active, 'kuota')),
                'filled' => array_sum(array_column($active, 'filled')),
            ],
            'holder_total' => $holderTotal,
            'outside_structure' => $holderTotal - array_sum(array_column($rows, 'holder_count')),
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

    /** Selisih dari versi $from menuju versi $to (unit baru pada $to tercatat sebagai unit.add). */
    public function compareVersions(StructureDecree $from, StructureDecree $to): StructureDiff
    {
        return $this->calculator->calculate($to->snapshot, $from->snapshot);
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
                'nik_masked' => NikMasker::mask((string) $row->employee_nik),
                'status_penugasan' => $row->status_penugasan,
            ];
        }

        return $grouped;
    }
}
