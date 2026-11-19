<?php

namespace App\Services\Structure;

/**
 * Membandingkan struktur target (lampiran SK) dengan struktur saat ini. Murni: tidak mengakses basis data.
 *
 * Bentuk struktur: ['units' => [namaUnit => ['kode', 'wilayah', 'urutan', 'is_active', 'seats' => [namaJabatan =>
 * ['rm_level', 'level', 'bidang', 'kuota']]]]]. Unit yang tidak ada pada target dan tidak ditutup tidak disentuh.
 */
class StructureDiffCalculator
{
    private const TYPE_ORDER = [
        'unit.rename' => 0, 'unit.add' => 1, 'unit.reactivate' => 2, 'unit.close' => 3,
        'position.rename' => 4, 'position.rm_change' => 5,
        'seat.add' => 6, 'seat.update' => 7, 'seat.remove' => 8, 'seat.move' => 9,
    ];

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $current
     * @param  array<string, string>  $unitMapping  [namaUnitLama => namaUnitBaru]
     * @param  list<string>  $closedUnits  nama unit saat ini yang ditutup
     * @param  array<string, string>  $positionMapping  [namaJabatanLama => namaJabatanBaru]
     */
    public function calculate(array $target, array $current, array $unitMapping = [], array $closedUnits = [], array $positionMapping = []): StructureDiff
    {
        $currentUnits = $current['units'] ?? [];
        $targetUnits = $target['units'] ?? [];

        // Unit saat ini menurut nama yang akan dipakainya setelah SK berlaku.
        $matched = [];
        foreach ($currentUnits as $old => $unit) {
            $matched[$unitMapping[$old] ?? $old] = $unit;
        }

        $changes = [];

        foreach ($unitMapping as $old => $new) {
            if ($old !== $new && isset($currentUnits[$old])) {
                $changes[] = ['type' => 'unit.rename', 'from' => $old, 'to' => $new];
            }
        }

        foreach ($positionMapping as $old => $new) {
            if ($old !== $new && $this->usesPosition($currentUnits, $old)) {
                $changes[] = ['type' => 'position.rename', 'from' => $old, 'to' => $new];
            }
        }

        foreach ($targetUnits as $name => $targetUnit) {
            $targetSeats = $targetUnit['seats'] ?? [];

            if (! isset($matched[$name])) {
                $changes[] = ['type' => 'unit.add', 'unit' => $name];
                foreach ($targetSeats as $jabatan => $seat) {
                    $changes[] = $this->addChange($name, $jabatan, $seat);
                }

                continue;
            }

            if (($matched[$name]['is_active'] ?? true) === false) {
                $changes[] = ['type' => 'unit.reactivate', 'unit' => $name];
            }

            $currentSeats = $this->renameSeats($matched[$name]['seats'] ?? [], $positionMapping);

            foreach ($targetSeats as $jabatan => $seat) {
                if (! isset($currentSeats[$jabatan])) {
                    $changes[] = $this->addChange($name, $jabatan, $seat);

                    continue;
                }

                $old = [];
                $new = [];
                foreach (['kuota', 'bidang'] as $field) {
                    if ($currentSeats[$jabatan][$field] !== $seat[$field]) {
                        $old[$field] = $currentSeats[$jabatan][$field];
                        $new[$field] = $seat[$field];
                    }
                }
                if ($new !== []) {
                    $changes[] = ['type' => 'seat.update', 'unit' => $name, 'jabatan' => $jabatan, 'old' => $old, 'new' => $new];
                }
            }

            foreach ($currentSeats as $jabatan => $seat) {
                if (! isset($targetSeats[$jabatan])) {
                    $changes[] = ['type' => 'seat.remove', 'unit' => $name, 'jabatan' => $jabatan, 'old' => ['kuota' => $seat['kuota'], 'bidang' => $seat['bidang']]];
                }
            }
        }

        foreach ($closedUnits as $name) {
            if (isset($currentUnits[$name]) && ($currentUnits[$name]['is_active'] ?? true) !== false) {
                $changes[] = ['type' => 'unit.close', 'unit' => $name];
            }
        }

        array_push($changes, ...$this->rmChanges($currentUnits, $targetUnits, $positionMapping));
        array_push($changes, ...$this->moves($changes));

        usort($changes, fn (array $a, array $b): int => [self::TYPE_ORDER[$a['type']], $a['unit'] ?? $a['from'] ?? '', $a['jabatan'] ?? '', $a['to_unit'] ?? '']
            <=> [self::TYPE_ORDER[$b['type']], $b['unit'] ?? $b['from'] ?? '', $b['jabatan'] ?? '', $b['to_unit'] ?? '']);

        return new StructureDiff($changes, [
            'before' => $this->totals($currentUnits, null),
            'after' => $this->totals($this->project($currentUnits, $targetUnits, $unitMapping, $closedUnits, $positionMapping), array_keys($targetUnits)),
        ]);
    }

    /** @return array<string, mixed> */
    private function addChange(string $unit, string $jabatan, array $seat): array
    {
        return ['type' => 'seat.add', 'unit' => $unit, 'jabatan' => $jabatan, 'rm_level' => $seat['rm_level'], 'level' => $seat['level'], 'bidang' => $seat['bidang'], 'kuota' => $seat['kuota']];
    }

    /** @param array<string, array> $units */
    private function usesPosition(array $units, string $jabatan): bool
    {
        foreach ($units as $unit) {
            if (isset(($unit['seats'] ?? [])[$jabatan])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array>  $seats
     * @param  array<string, string>  $positionMapping
     * @return array<string, array>
     */
    private function renameSeats(array $seats, array $positionMapping): array
    {
        $renamed = [];
        foreach ($seats as $jabatan => $seat) {
            $renamed[$positionMapping[$jabatan] ?? $jabatan] = $seat;
        }

        return $renamed;
    }

    /**
     * Level RM melekat pada jabatan (seluruh unit), jadi satu perubahan per jabatan.
     *
     * @return list<array<string, mixed>>
     */
    private function rmChanges(array $currentUnits, array $targetUnits, array $positionMapping): array
    {
        $currentRm = [];
        foreach ($currentUnits as $unit) {
            foreach ($this->renameSeats($unit['seats'] ?? [], $positionMapping) as $jabatan => $seat) {
                $currentRm[$jabatan] ??= $seat['rm_level'];
            }
        }

        $changes = [];
        $seen = [];
        foreach ($targetUnits as $unit) {
            foreach ($unit['seats'] ?? [] as $jabatan => $seat) {
                if (isset($seen[$jabatan])) {
                    continue;
                }
                $seen[$jabatan] = true;

                if (isset($currentRm[$jabatan]) && $currentRm[$jabatan] !== $seat['rm_level']) {
                    $changes[] = ['type' => 'position.rm_change', 'jabatan' => $jabatan, 'from' => $currentRm[$jabatan], 'to' => $seat['rm_level'], 'level' => $seat['level']];
                }
            }
        }

        return $changes;
    }

    /**
     * Informasi saja: jabatan yang dihapus dari satu unit dan ditambahkan ke unit lain.
     *
     * @param  list<array<string, mixed>>  $changes
     * @return list<array<string, mixed>>
     */
    private function moves(array $changes): array
    {
        $moves = [];
        foreach ($changes as $add) {
            if ($add['type'] !== 'seat.add') {
                continue;
            }
            foreach ($changes as $remove) {
                if ($remove['type'] === 'seat.remove' && $remove['jabatan'] === $add['jabatan'] && $remove['unit'] !== $add['unit']) {
                    $moves[] = ['type' => 'seat.move', 'jabatan' => $add['jabatan'], 'from_unit' => $remove['unit'], 'to_unit' => $add['unit']];

                    break;
                }
            }
        }

        return $moves;
    }

    /**
     * Struktur setelah SK berlaku: unit pada target menggantikan seat unit yang sama, unit yang ditutup hilang,
     * unit lain tetap.
     *
     * @return array<string, array>
     */
    private function project(array $currentUnits, array $targetUnits, array $unitMapping, array $closedUnits, array $positionMapping): array
    {
        $after = [];
        foreach ($currentUnits as $old => $unit) {
            if (in_array($old, $closedUnits, true)) {
                continue;
            }

            $name = $unitMapping[$old] ?? $old;
            $unit['seats'] = isset($targetUnits[$name])
                ? $targetUnits[$name]['seats'] ?? []
                : $this->renameSeats($unit['seats'] ?? [], $positionMapping);
            $after[$name] = $unit;
        }

        foreach ($targetUnits as $name => $unit) {
            $after[$name] ??= $unit;
        }

        return $after;
    }

    /**
     * @param  array<string, array>  $units
     * @param  list<string>|null  $reactivated  unit yang tercantum pada target dihitung aktif walau semula nonaktif
     * @return array<string, mixed>
     */
    private function totals(array $units, ?array $reactivated): array
    {
        $totals = ['units' => 0, 'pairs' => 0, 'kuota' => 0, 'kuota_by_rm' => [], 'kuota_by_bidang' => []];

        foreach ($units as $name => $unit) {
            if (($unit['is_active'] ?? true) !== false || in_array($name, $reactivated ?? [], true)) {
                $totals['units']++;
            }

            foreach ($unit['seats'] ?? [] as $seat) {
                $totals['pairs']++;
                $totals['kuota'] += $seat['kuota'];
                $totals['kuota_by_rm'][$seat['rm_level']] = ($totals['kuota_by_rm'][$seat['rm_level']] ?? 0) + $seat['kuota'];
                $totals['kuota_by_bidang'][$seat['bidang']] = ($totals['kuota_by_bidang'][$seat['bidang']] ?? 0) + $seat['kuota'];
            }
        }

        ksort($totals['kuota_by_rm']);
        ksort($totals['kuota_by_bidang']);

        return $totals;
    }
}
