<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Ekspor tabel Master Formasi (daftar unit atau formasi jabatan satu unit) sesuai pencarian dan filter yang sedang aktif.
 * Berkas memuat nama pengakses dan waktu unduh pada properti dokumen; tidak memuat data pribadi karyawan.
 */
class FormasiExport implements FromCollection, WithEvents, WithHeadings, WithMapping, WithProperties, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  Collection<int, list<scalar|null>>  $rows
     */
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $headings,
        private readonly Collection $rows,
        private readonly ?User $user = null,
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @param  list<scalar|null>  $row
     * @return list<scalar|null>
     */
    public function map($row): array
    {
        return array_map(fn ($value) => is_string($value) ? MutasiHistoryExport::sanitizeCellValue($value) : $value, $row);
    }

    public function properties(): array
    {
        $name = $this->user?->name ?? 'Administrator SDM';

        return [
            'creator' => $name,
            'lastModifiedBy' => 'PTPN IV Regional V HRIS System',
            'title' => $this->sheetTitle,
            'description' => 'Diunduh oleh '.$name.' pada '.now()->format('d/m/Y H:i:s').' WIB',
            'company' => 'PT Perkebunan Nusantara IV (Persero) Regional V',
            'category' => 'Master Formasi',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();

                $sheet->freezePane('A2');
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1B5330']],
                    'alignment' => ['vertical' => 'center'],
                ]);

                foreach (range('A', $lastColumn) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            },
        ];
    }
}
