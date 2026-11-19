<?php

namespace App\Services\Structure;

use App\Models\Unit;
use App\Services\Formasi\FormasiExcelParser;
use Illuminate\Support\Facades\DB;

/**
 * Membaca struktur organisasi dalam bentuk bersama (lihat StructureDiffCalculator): dari basis data atau dari hasil
 * baca lampiran SK. Jabatan Penampungan (Advisor, Diperbantukan) bukan formasi dan tidak ikut dibaca.
 */
class StructureReader
{
    /** @return array{units: array<string, array<string, mixed>>} */
    public function current(): array
    {
        $units = [];
        foreach (Unit::query()->orderBy('urutan')->orderBy('id')->get() as $unit) {
            $units[$unit->nama] = [
                'kode' => $unit->kode,
                'wilayah' => $unit->wilayah,
                'urutan' => $unit->urutan,
                'is_active' => (bool) $unit->is_active,
                'seats' => [],
            ];
        }

        $rows = DB::table('position_unit')
            ->join('units', 'units.id', '=', 'position_unit.unit_id')
            ->join('positions', 'positions.id', '=', 'position_unit.position_id')
            ->where('positions.is_overflow', false)
            ->where('positions.is_diperban', false)
            ->orderBy('position_unit.id')
            ->get(['units.nama as unit', 'positions.nama as jabatan', 'positions.rm_level', 'positions.level', 'position_unit.bidang', 'position_unit.kuota']);

        foreach ($rows as $row) {
            $units[$row->unit]['seats'][$row->jabatan] = [
                'rm_level' => $row->rm_level,
                'level' => $row->level,
                'bidang' => $row->bidang,
                'kuota' => (int) $row->kuota,
            ];
        }

        return ['units' => $units];
    }

    /**
     * @param  list<array{unit: string, jabatan: string, rm_level: string, bidang: string, kuota: int}>  $seats  keluaran FormasiExcelParser
     * @return array{units: array<string, array<string, mixed>>}
     */
    public function fromParsedSeats(array $seats): array
    {
        $units = [];
        foreach ($seats as $seat) {
            $units[$seat['unit']]['seats'][$seat['jabatan']] = [
                'rm_level' => $seat['rm_level'],
                'level' => FormasiExcelParser::levelForRm($seat['rm_level']),
                'bidang' => $seat['bidang'],
                'kuota' => $seat['kuota'],
            ];
        }

        return ['units' => $units];
    }
}
