<?php

namespace App\Exports\Sheets;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Katalog Formasi Jabatan per unit pada template impor: satu baris = satu Formasi Jabatan (unit, jabatan, strata,
 * RM Band, bidang, kuota, terisi, sisa), diambil dari Master Formasi saat template diunduh.
 *
 * Baris dikelompokkan per unit (berurutan). Dropdown Jabatan pada sheet data memanfaatkan pengelompokan itu:
 * MATCH menemukan baris pertama unit yang dipilih dan COUNTIF menghitung jumlah barisnya, sehingga OFFSET
 * mengambil tepat jabatan unit tersebut (lihat positionListFormula). Karena itu urutan baris tidak boleh diubah,
 * dan sheet dikunci dari pengurutan (penyaringan tetap boleh). Nama unit tidak boleh memuat karakter pembanding
 * Excel (* ? ~); nama unit master saat ini tidak memuatnya.
 */
class EmployeeTemplateFormasiSheet implements FromArray, WithEvents, WithHeadings, WithStrictNullComparison, WithTitle
{
    public const TITLE = 'Formasi per Unit';

    private const NOTE = 'Salinan Master Formasi saat template diunduh. Unduh ulang template bila Master Formasi berubah.';

    /** @var list<array<string, mixed>>|null */
    private ?array $seats = null;

    public function title(): string
    {
        return self::TITLE;
    }

    public function headings(): array
    {
        return ['UNIT KERJA', 'JABATAN', 'STRATA', 'RM BAND', 'BIDANG', 'KUOTA', 'TERISI', 'SISA KUOTA', '', 'CATATAN'];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->seats() as $index => $seat) {
            $rows[] = [
                $seat['unit'], $seat['jabatan'], $seat['level'], $seat['rm_level'],
                $seat['bidang'], $seat['kuota'], $seat['terisi'], $seat['sisa'],
                '',
                $index === 0 ? self::NOTE : '',
            ];
        }

        return $rows;
    }

    /**
     * Baris terakhir katalog (minimal 2 agar rumus validasi tetap sah pada master kosong).
     */
    public function lastSeatRow(): int
    {
        return max(2, count($this->seats()) + 1);
    }

    /**
     * Rumus dropdown Jabatan untuk sel pada baris $row, bergantung pada Unit Kerja di kolom I baris yang sama.
     */
    public function positionListFormula(int $row): string
    {
        $unitColumn = "'".self::TITLE."'!\$A\$2:\$A\${$this->lastSeatRow()}";

        return "OFFSET('".self::TITLE."'!\$B\$1,MATCH(\$I{$row},{$unitColumn},0),0,COUNTIF({$unitColumn},\$I{$row}),1)";
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->lastSeatRow();

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:H{$lastRow}");
                $sheet->getRowDimension(1)->setRowHeight(26);

                foreach (['A1:H1', 'J1'] as $header) {
                    $sheet->getStyle($header)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5330']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    ]);
                }

                foreach (['A' => 38, 'B' => 52, 'C' => 10, 'D' => 10, 'E' => 10, 'F' => 9, 'G' => 9, 'H' => 12, 'I' => 3, 'J' => 30] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
                $sheet->getStyle("C2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Katalog bersifat acuan: dikunci tanpa kata sandi. Penyaringan boleh, pengurutan tidak (merusak
                // pengelompokan per unit yang dipakai dropdown Jabatan).
                $protection = $sheet->getProtection();
                $protection->setSheet(true);
                $protection->setAutoFilter(false);
                $protection->setSort(true);
            },
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function seats(): array
    {
        if ($this->seats !== null) {
            return $this->seats;
        }

        $terisi = Employee::query()
            ->where('status_penugasan', 'Definitif')
            ->selectRaw('unit_kerja, jabatan, count(*) as total')
            ->groupBy('unit_kerja', 'jabatan')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->unit_kerja.'|'.$row->jabatan => (int) $row->total]);

        $records = DB::table('position_unit')
            ->join('units', 'units.id', '=', 'position_unit.unit_id')
            ->join('positions', 'positions.id', '=', 'position_unit.position_id')
            ->where('units.is_active', true)
            ->where('positions.is_active', true)
            ->orderBy('units.urutan')
            ->orderBy('units.nama')
            ->orderByRaw("case positions.level when 'Karpim' then 0 else 1 end")
            ->orderBy('positions.rm_level')
            ->orderBy('positions.nama')
            ->get([
                'units.nama as unit', 'positions.nama as jabatan', 'positions.level', 'positions.rm_level',
                'position_unit.bidang', 'position_unit.kuota', 'positions.is_overflow', 'positions.is_diperban',
            ]);

        return $this->seats = $records->map(function ($record) use ($terisi): array {
            $isPool = $record->is_overflow || $record->is_diperban;
            $filled = $terisi[$record->unit.'|'.$record->jabatan] ?? 0;

            return [
                'unit' => $record->unit,
                'jabatan' => $record->jabatan,
                'level' => $record->level,
                'rm_level' => $record->rm_level ?? '',
                'bidang' => $record->bidang ?? '',
                'kuota' => (int) $record->kuota,
                'terisi' => $filled,
                'sisa' => $isPool ? 'Tanpa batas' : max(0, (int) $record->kuota - $filled),
            ];
        })->all();
    }
}
