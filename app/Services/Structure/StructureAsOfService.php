<?php

namespace App\Services\Structure;

use App\Models\JobHistory;
use App\Models\Position;
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
    /** Why a holder is not matched to a formasi of the decree (drives the corrective hint shown to HR admins). */
    public const REASON_POOL = 'penampungan';

    public const REASON_UNIT_MISSING = 'unit_tidak_ada';

    public const REASON_OTHER_UNIT = 'jabatan_di_unit_lain';

    public const REASON_NAME_MISMATCH = 'nama_jabatan_berbeda';

    public const REASON_JABATAN_MISSING = 'jabatan_tidak_ada';

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
     * @return array{decree: StructureDecree, rows: list<array<string, mixed>>, units: list<array<string, mixed>>, totals: array{units: int, seats: int, kuota: int, filled: int}, holder_total: int, outside_structure: int, concurrent_outside: int, concurrent_holders: list<array{unit: string, jabatan: string, nama: string, nik_masked: string, status_penugasan: ?string}>, outside_holders: list<array{unit: string, jabatan: string, nama: string, nik_masked: string, status_penugasan: ?string, reason: string, hint: ?string}>}|null
     */
    public function report(CarbonInterface $date): ?array
    {
        $decree = $this->decreeFor($date);
        if ($decree === null) {
            return null;
        }

        $holders = $this->holders($date);
        $snapshotUnits = $decree->snapshot['units'] ?? [];
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

        // Whatever is left in $holders was not matched to a formasi (e.g. Jabatan Penampungan or a unit no longer in the SK).
        // A concurrent assignment (Plt/Pj, e.g. from the import's Tugas Rangkap column) is a valid extra duty, not a formasi seat:
        // it is reported on its own and never as a mismatch to fix.
        $outsideHolders = [];
        $concurrentHolders = [];
        foreach ($holders as $unit => $byJabatan) {
            foreach ($byJabatan as $jabatan => $placed) {
                foreach ($placed as $holder) {
                    if ($holder['status_penugasan'] !== null && $holder['status_penugasan'] !== 'Definitif') {
                        $concurrentHolders[] = ['unit' => (string) $unit, 'jabatan' => (string) $jabatan] + $holder;

                        continue;
                    }
                    $outsideHolders[] = ['unit' => (string) $unit, 'jabatan' => (string) $jabatan] + $holder + $this->classifyOutside((string) $unit, (string) $jabatan, $snapshotUnits);
                }
            }
        }

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
            'outside_structure' => count($outsideHolders),
            'concurrent_outside' => count($concurrentHolders),
            'concurrent_holders' => $concurrentHolders,
            'outside_holders' => $outsideHolders,
        ];
    }

    /**
     * Diagnoses a holder that matched no formasi, so the admin knows what to correct (through Mutasi) instead of guessing.
     * Matching is exact on unit + jabatan, hence most cases are a truncated/renamed jabatan or a wrong unit, not a real
     * position outside the structure. A name hint is given only when exactly one seat of the unit is a plausible match.
     *
     * @param  array<string, array<string, mixed>>  $snapshotUnits
     * @return array{reason: string, hint: ?string}
     */
    private function classifyOutside(string $unit, string $jabatan, array $snapshotUnits): array
    {
        $position = Position::getAllCached()->get($jabatan);
        if ($position && ($position->is_overflow || $position->is_diperban)) {
            return ['reason' => self::REASON_POOL, 'hint' => null];
        }

        if (! isset($snapshotUnits[$unit])) {
            return ['reason' => self::REASON_UNIT_MISSING, 'hint' => null];
        }

        $otherUnits = [];
        foreach ($snapshotUnits as $name => $data) {
            if ($name !== $unit && isset(($data['seats'] ?? [])[$jabatan])) {
                $otherUnits[] = (string) $name;
            }
        }
        if ($otherUnits !== []) {
            return ['reason' => self::REASON_OTHER_UNIT, 'hint' => implode('; ', array_slice($otherUnits, 0, 3))];
        }

        $needle = $this->normalize($jabatan);
        $candidates = array_values(array_filter(
            array_map('strval', array_keys($snapshotUnits[$unit]['seats'] ?? [])),
            function (string $seat) use ($needle): bool {
                $seat = $this->normalize($seat);

                return $needle !== '' && $seat !== '' && (str_contains($seat, $needle) || str_contains($needle, $seat));
            }
        ));

        return count($candidates) === 1
            ? ['reason' => self::REASON_NAME_MISMATCH, 'hint' => $candidates[0]]
            : ['reason' => self::REASON_JABATAN_MISSING, 'hint' => null];
    }

    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($text)));
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
