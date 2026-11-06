<?php

namespace Tests\Feature\DataTable;

use App\Exports\DataTableExport;
use App\Models\User;
use App\Support\DataTable\Column;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Events\AfterSheet;
use Tests\Fixtures\DataTable\FixtureRow;
use Tests\TestCase;

class DataTableExportTest extends TestCase
{
    private function export(?User $user = null): DataTableExport
    {
        $columns = [
            Column::make('nama', 'Nama')->exportable(),
            Column::make('layar', 'Layar'),
            Column::make('kode', 'Kode')->exportable('Kode Unik'),
            Column::make('rahasia', 'Rahasia')->exportable('Tersembunyi', fn ($row) => 'x-'.$row->kode)->exportOnly(),
        ];

        return new DataTableExport(FixtureRow::query(), $columns, 'Tabel Uji', $user);
    }

    public function test_headings_and_rows_follow_exportable_columns(): void
    {
        $export = $this->export();
        $row = new FixtureRow(['nama' => 'Budi', 'kode' => 'K01']);

        $this->assertSame(['Nama', 'Kode Unik', 'Tersembunyi'], $export->headings());
        $this->assertSame(['Budi', 'K01', 'x-K01'], $export->map($row));
    }

    public function test_formula_prefixes_are_neutralised(): void
    {
        $export = $this->export();

        foreach (['=SUM(A1)', '+1', '-1', '@cmd', "\tx", "\rx"] as $payload) {
            $this->assertSame("'".$payload, $export->map(new FixtureRow(['nama' => $payload, 'kode' => 'K']))[0]);
        }

        $this->assertSame('aman', $export->map(new FixtureRow(['nama' => 'aman', 'kode' => null]))[0]);
        $this->assertSame('', $export->map(new FixtureRow(['nama' => 'aman', 'kode' => null]))[1]);
    }

    public function test_chunk_size_is_500(): void
    {
        // Query exports only honour WithCustomChunkSize; WithChunkReading applies to imports.
        $this->assertInstanceOf(WithCustomChunkSize::class, $this->export());
        $this->assertSame(500, $this->export()->chunkSize());
    }

    public function test_properties_name_the_downloader(): void
    {
        $this->travelTo('2026-11-06 10:00:00');
        $user = User::factory()->make(['name' => 'Siti Admin']);

        $properties = $this->export($user)->properties();

        $this->assertSame('Siti Admin', $properties['creator']);
        $this->assertSame('Tabel Uji', $properties['title']);
        $this->assertSame('Dokumen rahasia — diunduh oleh Siti Admin pada 06-11-2026 10:00', $properties['description']);
        $this->assertArrayHasKey(AfterSheet::class, $this->export($user)->registerEvents());
    }
}
