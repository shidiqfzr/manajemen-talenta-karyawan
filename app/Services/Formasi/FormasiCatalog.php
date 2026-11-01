<?php

namespace App\Services\Formasi;

use Illuminate\Support\Facades\DB;

/**
 * Model baca atas formasi jabatan (position_unit): bidang per formasi jabatan dan
 * Standar Formasi per Level RM x Bidang. Dimuat sekali per instans.
 */
class FormasiCatalog
{
    private const RM_LEVELS = ['RM-1', 'RM-2', 'RM-3'];

    private const BIDANG = ['KEU', 'TAN', 'TEK', 'UMU'];

    /** @var array<string, string>|null  "unit|jabatan" => bidang */
    private ?array $seatBidang = null;

    /** @var array<string, array<string, int>>|null */
    private ?array $standarFormasi = null;

    /**
     * Bidang formasi jabatan pada (unit, jabatan); null bila tidak ada formasinya
     * (mis. jabatan pool Advisor/Diperbantukan atau unit tidak dikenal).
     */
    public function seatBidang(string $unitNama, string $jabatan): ?string
    {
        $this->load();

        return $this->seatBidang[$unitNama.'|'.$jabatan] ?? null;
    }

    /**
     * Standar Formasi per Level RM dan Bidang (jumlah kuota formasi jabatan definitif).
     *
     * @return array<string, array<string, int>>
     */
    public function standarFormasi(): array
    {
        $this->load();

        return $this->standarFormasi;
    }

    private function load(): void
    {
        if ($this->seatBidang !== null) {
            return;
        }

        $matrix = [];
        foreach (self::RM_LEVELS as $rm) {
            $matrix[$rm] = array_fill_keys(self::BIDANG, 0);
        }

        $seatBidang = [];

        $seats = DB::table('position_unit')
            ->join('positions', 'positions.id', '=', 'position_unit.position_id')
            ->join('units', 'units.id', '=', 'position_unit.unit_id')
            ->get([
                'units.nama as unit',
                'positions.nama as jabatan',
                'positions.rm_level',
                'positions.is_overflow',
                'positions.is_diperban',
                'position_unit.kuota',
                'position_unit.bidang',
            ]);

        foreach ($seats as $seat) {
            if ($seat->bidang === null) {
                continue;
            }

            $seatBidang[$seat->unit.'|'.$seat->jabatan] = $seat->bidang;

            if (! $seat->is_overflow && ! $seat->is_diperban && isset($matrix[$seat->rm_level][$seat->bidang])) {
                $matrix[$seat->rm_level][$seat->bidang] += (int) $seat->kuota;
            }
        }

        $this->seatBidang = $seatBidang;
        $this->standarFormasi = $matrix;
    }
}
