<?php

namespace App\Exports;

use App\Models\User;
use App\Support\DataTable\Column;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Generic Excel export for shared data tables: exports the columns flagged exportable,
 * neutralises spreadsheet formulas and stamps the confidentiality notice.
 */
final class DataTableExport implements FromQuery, WithChunkReading, WithEvents, WithHeadings, WithMapping, WithProperties
{
    /** @var array<int, Column> */
    private array $columns;

    /** @param array<int, Column> $columns */
    public function __construct(
        private Builder $query,
        array $columns,
        private string $title,
        private ?User $user,
    ) {
        $this->columns = array_values(array_filter($columns, fn (Column $column) => $column->isExportable()));
    }

    public function query(): Builder
    {
        return $this->query;
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return array_map(fn (Column $column) => $column->exportHeading(), $this->columns);
    }

    /** @return array<int, string> */
    public function map($row): array
    {
        return array_map(
            fn (Column $column) => $this->neutralise((string) ($column->exportValue($row) ?? '')),
            $this->columns,
        );
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /** @return array<string, string> */
    public function properties(): array
    {
        return [
            'creator' => $this->downloader(),
            'lastModifiedBy' => 'PTPN IV Regional V HRIS System',
            'title' => $this->title,
            'description' => $this->notice(),
            'category' => 'HR Confidential Document',
            'company' => 'PT Perkebunan Nusantara IV (Persero) Regional V',
        ];
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getHeaderFooter()->setOddFooter(
                    '&L&8'.str_replace('&', '&&', $this->notice()).'&R&8Hal &P dari &N'
                );
            },
        ];
    }

    /** Prefixes a quote so spreadsheet apps never evaluate the cell as a formula. */
    public function neutralise(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function downloader(): string
    {
        return $this->user?->name ?? 'Administrator HRIS';
    }

    private function notice(): string
    {
        return 'Dokumen rahasia — diunduh oleh '.$this->downloader().' pada '.now()->format('d-m-Y H:i');
    }
}
