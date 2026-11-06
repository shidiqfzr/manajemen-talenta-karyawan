<?php

namespace App\Exports;

use App\Exports\Sheets\EmployeeTemplateDataSheet;
use App\Exports\Sheets\EmployeeTemplateFormasiSheet;
use App\Exports\Sheets\EmployeeTemplateReferenceSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EmployeeTemplateExport implements WithMultipleSheets
{
    /**
     * Mengembalikan multi-sheet workbook:
     * 1. Sheet 'Data Karyawan' dengan in-cell dropdown data validations (Jabatan mengikuti Unit Kerja).
     * 2. Sheet 'Formasi per Unit' memuat katalog Formasi Jabatan (strata, RM Band, bidang, kuota, sisa).
     * 3. Sheet 'Referensi Master Data' memuat Unit Kerja, Golongan, dan kamus kategori.
     */
    public function sheets(): array
    {
        return [
            new EmployeeTemplateDataSheet,
            new EmployeeTemplateFormasiSheet,
            new EmployeeTemplateReferenceSheet,
        ];
    }
}
