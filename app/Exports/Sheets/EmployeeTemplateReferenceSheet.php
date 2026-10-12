<?php

namespace App\Exports\Sheets;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EmployeeTemplateReferenceSheet implements FromArray, WithEvents, WithHeadings, WithTitle
{
    public int $maxUnitRow = 2;

    public int $maxPositionRow = 2;

    public function title(): string
    {
        return 'Referensi Master Data';
    }

    public function headings(): array
    {
        return [
            '43 UNIT KERJA RESMI',
            'JABATAN FORMASI AKTIF',
            'STRATA PEGAWAI',
            'RM LEVEL',
            'STATUS KARYAWAN',
            'STATUS PENUGASAN',
            'JALUR MASUK',
            'AGAMA RESMI',
            'SUSUNAN KELUARGA (PTKP)',
            'PENDIDIKAN TERAKHIR',
            'CONTOH FORMAT GOLONGAN',
        ];
    }

    public function array(): array
    {
        // 1. Ambil 43 Unit Kerja resmi
        $activeUnits = Unit::active()->orderBy('urutan')->orderBy('nama')->pluck('nama')->toArray();
        if (empty($activeUnits)) {
            $activeUnits = Employee::OFFICIAL_UNITS;
        }

        // 2. Ambil Jabatan aktif
        $activePositions = Position::active()->orderBy('level')->orderBy('nama')->pluck('nama')->toArray();
        if (empty($activePositions)) {
            $activePositions = [
                'Manajer Kebun', 'Askep Tanaman', 'Asisten Afdeling', 'Asisten Tata Usaha (KTU)',
                'Manajer Pabrik', 'Askep Pengolahan (Masinis Kepala)', 'Asisten Pengolahan',
                'Asisten Laboratorium / QC', 'Mandor Panen', 'Mandor Pemeliharaan',
                'Operator PKS', 'Krani Afdeling', 'Mekanik Bengkel', 'Satpam / Keamanan Kebun',
            ];
        }

        // 3. Konstanta Domain sesuai DOMAIN.md
        $levels = array_keys(Employee::LEVELS);
        $rmLevels = array_keys(Employee::RM_LEVELS);
        $employmentStatuses = ['Tetap (PKWTT)', 'Kontrak (PKWT)', 'Calon Pegawai'];
        $assignmentStatuses = ['Definitif', 'Diperbantukan', 'Pejabat Sementara (Pjs)', 'Tugas Belajar', 'Advisor'];
        $entryChannels = array_keys(Employee::ENTRY_CHANNELS);
        $religions = Employee::RELIGIONS;
        $familyStatuses = array_keys(Employee::FAMILY_STATUSES);
        $educationLevels = Employee::EDUCATION_LEVELS;
        $golongans = [
            'IA/00', 'IB/00', 'IC/00', 'ID/00',
            'IIA/00', 'IIB/00', 'IIC/00', 'IID/00',
            'IIIA/00', 'IIIB/00', 'IIIC/00', 'IIID/00',
            'IVA/00', 'IVB/00', 'IVC/00', 'IVD/00',
        ];

        $this->maxUnitRow = count($activeUnits) + 1;
        $this->maxPositionRow = count($activePositions) + 1;

        $maxCount = max(
            count($activeUnits),
            count($activePositions),
            count($levels),
            count($rmLevels),
            count($employmentStatuses),
            count($assignmentStatuses),
            count($entryChannels),
            count($religions),
            count($familyStatuses),
            count($educationLevels),
            count($golongans)
        );

        $rows = [];
        for ($i = 0; $i < $maxCount; $i++) {
            $rows[] = [
                $activeUnits[$i] ?? '',
                $activePositions[$i] ?? '',
                $levels[$i] ?? '',
                $rmLevels[$i] ?? '',
                $employmentStatuses[$i] ?? '',
                $assignmentStatuses[$i] ?? '',
                $entryChannels[$i] ?? '',
                $religions[$i] ?? '',
                $familyStatuses[$i] ?? '',
                $educationLevels[$i] ?? '',
                $golongans[$i] ?? '',
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Auto size columns A sampai K
                foreach (range('A', 'K') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Header styling: Slate corporate dengan font putih tebal
                $sheet->getStyle('A1:K1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size' => 10,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1E293B'], // Slate 800
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(26);
            },
        ];
    }
}
