<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Berkas unduhan baris gagal: lembar pertama tetap berbentuk templat impor (dapat diunggah ulang),
 * lembar kedua memuat nama pengakses dan waktu unduh karena berkas berisi data pribadi (DESIGN.md §11).
 * Impor hanya membaca lembar pertama.
 */
class FailedEmployeesWorkbook implements WithMultipleSheets
{
    public function __construct(
        protected array $failedRows,
        protected string $accessorName,
        protected string $accessedAt
    ) {}

    public function sheets(): array
    {
        $info = [
            ['Dokumen rahasia: berisi data pribadi pegawai (UU No. 27/2022). Dilarang disebarluaskan.'],
            ['Diunduh oleh', $this->accessorName],
            ['Waktu unduh', $this->accessedAt],
            ['Jumlah baris gagal', count($this->failedRows)],
        ];

        return [
            new FailedEmployeesExport($this->failedRows),
            new class($info) implements FromArray, ShouldAutoSize, WithTitle
            {
                public function __construct(private array $rows) {}

                public function array(): array
                {
                    return $this->rows;
                }

                public function title(): string
                {
                    return 'Informasi Unduhan';
                }
            },
        ];
    }
}
