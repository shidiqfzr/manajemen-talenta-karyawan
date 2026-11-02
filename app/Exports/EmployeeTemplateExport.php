<?php

namespace App\Exports;

use App\Exports\Sheets\EmployeeTemplateDataSheet;
use App\Exports\Sheets\EmployeeTemplateReferenceSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EmployeeTemplateExport implements WithMultipleSheets
{
    /**
     * Mengembalikan multi-sheet workbook:
     * 1. Sheet 'Data Karyawan' dengan in-cell dropdown data validations.
     * 2. Sheet 'Referensi Master Data' memuat Unit Kerja, Posisi, Golongan, dan kamus kategori.
     */
    public function sheets(): array
    {
        return [
            new EmployeeTemplateDataSheet,
            new EmployeeTemplateReferenceSheet,
        ];
    }
}
