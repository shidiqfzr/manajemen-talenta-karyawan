<?php

namespace Tests\Unit\DataTable;

use App\Models\Employee;
use App\Support\DataTable\Column;
use InvalidArgumentException;
use Tests\TestCase;

class ColumnTest extends TestCase
{
    public function test_fluent_calls_return_new_instances(): void
    {
        $original = Column::make('nama', 'Nama');
        $sortable = $original->sortable();

        $this->assertNotSame($original, $sortable);
        $this->assertFalse($original->isSortable());
        $this->assertTrue($sortable->isSortable());
        $this->assertSame('left', $original->alignment);
        $this->assertSame('right', $original->align('right')->alignment);
    }

    public function test_export_heading_defaults_to_label(): void
    {
        $column = Column::make('nama', 'Nama Pegawai');

        $this->assertSame('Nama Pegawai', $column->exportHeading());
        $this->assertSame('Nama', $column->exportable('Nama')->exportHeading());
        $this->assertTrue($column->exportable()->isExportable());
        $this->assertTrue($column->exportOnly()->isExportOnly());
    }

    public function test_export_value_uses_closure_or_data_get(): void
    {
        $row = new Employee(['nama' => 'Budi']);

        $this->assertSame('Budi', Column::make('nama', 'Nama')->exportValue($row));
        $custom = Column::make('nama', 'Nama')->exportable(null, fn ($r) => strtoupper($r->nama));
        $this->assertSame('BUDI', $custom->exportValue($row));
    }

    public function test_invalid_alignment_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Column::make('nama', 'Nama')->align('justify');
    }

    public function test_width_is_kept_on_a_clone(): void
    {
        $original = Column::make('nama', 'Nama');
        $wide = $original->width('w-[18%]');

        $this->assertNull($original->width);
        $this->assertSame('w-[18%]', $wide->width);
        $this->assertNotSame($original, $wide);
    }
}
