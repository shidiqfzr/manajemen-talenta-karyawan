<?php

namespace Tests\Unit;

use App\Support\JabatanLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Penulisan nama jabatan untuk tampilan: "dan" menjadi "&", tanpa menyentuh kata lain. */
class JabatanLabelTest extends TestCase
{
    #[DataProvider('names')]
    public function test_dan_is_displayed_as_an_ampersand(string $stored, string $displayed): void
    {
        $this->assertSame($displayed, JabatanLabel::display($stored));
    }

    /** @return array<string, array{string, string}> */
    public static function names(): array
    {
        return [
            'simple' => ['Asisten Tata Usaha dan Keuangan', 'Asisten Tata Usaha & Keuangan'],
            'roman numerals' => ['Asisten Afdeling I dan II', 'Asisten Afdeling I & II'],
            'serial comma is dropped' => ['Key User Modul PP, QM, dan Jembatan Timbang', 'Key User Modul PP, QM & Jembatan Timbang'],
            'already ampersand' => ['Asisten Teknik & Pengolahan', 'Asisten Teknik & Pengolahan'],
            'no conjunction' => ['Kepala Bagian Tanaman', 'Kepala Bagian Tanaman'],
            'words containing dan are untouched' => ['Asisten Perdanaan Pandan', 'Asisten Perdanaan Pandan'],
        ];
    }
}
