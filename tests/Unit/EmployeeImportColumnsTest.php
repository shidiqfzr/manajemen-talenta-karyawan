<?php

namespace Tests\Unit;

use App\Exports\EmployeesExport;
use App\Exports\Sheets\EmployeeTemplateDataSheet;
use App\Models\Employee;
use App\Services\Import\EmployeeImportColumns as Columns;
use App\Services\Import\EmployeeImportDictionary;
use Tests\TestCase;

/**
 * SUT: App\Services\Import\EmployeeImportColumns, satu-satunya definisi kolom spreadsheet karyawan. Uji ini menjaga agar
 * template, ekspor, berkas baris gagal, dan kamus format tidak dapat berbeda satu sama lain.
 */
class EmployeeImportColumnsTest extends TestCase
{
    public function test_the_template_has_24_columns_with_unit_before_jabatan_and_the_tabular_layout_has_26(): void
    {
        $template = Columns::fields(Columns::LAYOUT_TEMPLATE);
        $tabular = Columns::fields(Columns::LAYOUT_TABULAR);

        $this->assertCount(24, $template);
        $this->assertCount(26, $tabular);
        $this->assertSame('X', Columns::lastLetter(Columns::LAYOUT_TEMPLATE));
        $this->assertSame('Z', Columns::lastLetter(Columns::LAYOUT_TABULAR));
        $this->assertSame(range('A', 'X'), Columns::letters(Columns::LAYOUT_TEMPLATE));

        // Dropdown Jabatan bergantung pada Unit Kerja, jadi unit selalu lebih dulu pada template.
        $this->assertSame('I', Columns::letter('unit_kerja'));
        $this->assertSame('J', Columns::letter('jabatan'));
        $this->assertSame(['level', 'rm_level'], array_values(array_diff($tabular, $template)), 'Strata dan RM Band diturunkan dari formasi, bukan kolom template.');

        foreach ([$template, $tabular] as $layout) {
            $this->assertSame($layout, array_values(array_unique($layout)), 'Tidak ada kolom ganda.');
        }
    }

    public function test_headings_carry_the_required_marker_only_where_asked(): void
    {
        $this->assertSame(
            ['NIK*', 'NAMA*', 'JENIS KELAMIN', 'TEMPAT LAHIR', 'TANGGAL LAHIR', 'AGAMA', 'SUSUNAN KELUARGA', 'STATUS KARYAWAN*', 'UNIT KERJA*', 'JABATAN*'],
            array_slice(Columns::headings(Columns::LAYOUT_TEMPLATE, true), 0, 10)
        );
        $this->assertSame(['NIK', 'NAMA', 'JENIS KELAMIN'], array_slice(Columns::headings(Columns::LAYOUT_TABULAR), 0, 3));
        $this->assertContains('LEVEL*', Columns::headings(Columns::LAYOUT_TABULAR, true));
        $this->assertSame(['nik', 'nama', 'status_karyawan', 'unit_kerja', 'jabatan'], Columns::requiredFields());
        $this->assertSame(['nik', 'nama', 'status_karyawan', 'jabatan', 'level', 'unit_kerja'], Columns::requiredFields(Columns::LAYOUT_TABULAR));
    }

    public function test_date_columns_and_letters_follow_the_layout(): void
    {
        $this->assertSame(['E', 'N', 'O', 'P', 'Q', 'W', 'X'], array_map(Columns::letter(...), Columns::fieldsOfType(Columns::TYPE_DATE)));
        $this->assertSame(['E', 'P', 'Q', 'R', 'S', 'Y', 'Z'], array_map(fn (string $field) => Columns::letter($field, Columns::LAYOUT_TABULAR), Columns::fieldsOfType(Columns::TYPE_DATE, Columns::LAYOUT_TABULAR)));
        $this->assertSame('M', Columns::letter('tugas_rangkap_penugasan_sementara'));
        $this->assertSame('O', Columns::letter('tugas_rangkap_penugasan_sementara', Columns::LAYOUT_TABULAR));
    }

    public function test_a_field_missing_from_the_layout_is_an_error_not_a_wrong_letter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Columns::letter('level');
    }

    public function test_old_and_new_status_headings_resolve_to_the_same_field(): void
    {
        $this->assertSame('status_jabatan_utama', Columns::headingKey('status_penugasan'));
        $this->assertSame('status_penugasan', Columns::fieldForHeadingKey('status_jabatan_utama'));
        $this->assertSame('status_penugasan', Columns::fieldForHeadingKey('status_penugasan'));
        $this->assertSame('nik', Columns::fieldForHeadingKey(Columns::normaliseHeading('NIK*')));
        $this->assertSame('kolom_asing', Columns::fieldForHeadingKey('kolom_asing'));
        $this->assertSame('tugas_rangkap_penugasan_sementara', Columns::fieldForHeadingKey(Columns::normaliseHeading('TUGAS RANGKAP / PENUGASAN SEMENTARA')));
    }

    public function test_raw_keys_list_the_field_and_its_aliases_and_labels_exist_for_every_field(): void
    {
        $this->assertSame(['status_penugasan', 'status_jabatan_utama'], Columns::rawKeys('status_penugasan'));
        $this->assertSame(['jenis_kelamin', 'jk'], Columns::rawKeys('jenis_kelamin'));
        $this->assertSame('Status Jabatan Utama', Columns::label('status_penugasan'));

        foreach (Columns::fields(Columns::LAYOUT_TABULAR) as $field) {
            $this->assertNotSame('', Columns::label($field));
            $this->assertSame($field, Columns::rawKeys($field)[0]);
        }
    }

    public function test_every_consumer_reads_the_same_columns(): void
    {
        // Template dan ekspor tidak punya salinan sendiri.
        $this->assertSame(Columns::headings(Columns::LAYOUT_TEMPLATE, true), (new EmployeeTemplateDataSheet)->headings());
        $this->assertSame(Columns::headings(Columns::LAYOUT_TABULAR), (new EmployeesExport)->headings());

        // Kamus format: huruf, kunci, dan judul kategori turun dari daftar yang sama dan urutannya cocok dengan template.
        $guide = EmployeeImportDictionary::getColumns();
        $this->assertSame(Columns::fields(Columns::LAYOUT_TEMPLATE), array_column($guide, 'field'));
        $this->assertSame(Columns::letters(), array_column($guide, 'letter'));
        $this->assertSame(array_map(Columns::headingKey(...), Columns::fields()), array_column($guide, 'key'));
        $ranges = array_map(fn (array $category) => preg_match('/\(Kolom ([A-Z] – [A-Z])\)/u', $category['title'], $match) ? $match[1] : null, EmployeeImportDictionary::getCategories());
        $this->assertSame(['A – G', 'H – M', 'N – T', 'U – X'], $ranges);
        $this->assertSame(['7 Kolom', '6 Kolom', '7 Kolom', '4 Kolom'], array_column(EmployeeImportDictionary::getCategories(), 'count_label'));
    }

    public function test_assignment_statuses_are_defined_once(): void
    {
        $this->assertSame(
            [Employee::STATUS_DEFINITIF, Employee::STATUS_DIPERBANTUKAN, Employee::STATUS_PJS, Employee::STATUS_TUGAS_BELAJAR, Employee::STATUS_ADVISOR],
            Employee::ASSIGNMENT_STATUSES
        );
        $this->assertSame('Pejabat Sementara (Pjs)', Employee::STATUS_PJS);
        $this->assertSame([Employee::STATUS_DEFINITIF, Employee::STATUS_PJS, Employee::STATUS_TUGAS_BELAJAR], Employee::ASSIGNMENT_STATUSES_TEMPLATE);
    }
}
