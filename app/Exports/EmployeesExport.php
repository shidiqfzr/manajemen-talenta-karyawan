<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class EmployeesExport implements FromCollection, WithHeadings, WithEvents
{
    protected $filters;

    protected $columns = [
        'nik',
        'nama',
        'jabatan',
        'level',
        'unit_kerja',
        'golongan',
        'tanggal_dalam_jabatan',
        'tmt_unit_kerja',
        'tempat_lahir',
        'tanggal_lahir',
        'tmt_bekerja',
        'tanggal_diangkat_staf',
        'susunan_keluarga',
        'job_grader',
        'person_grade',
        'tanggal_mbt',
        'tanggal_pensiun',
        'agama',
        'pendidikan_terakhir',
        'sekolah'
    ];

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Employee::query();

        if (!empty($this->filters['search'])) {
            $query->where(function ($q) {
                $q->where('nik', 'like', '%' . $this->filters['search'] . '%')
                    ->orWhere('nama', 'like', '%' . $this->filters['search'] . '%');
            });
        }

        if (!empty($this->filters['jabatan'])) {
            $query->where('jabatan', $this->filters['jabatan']);
        }

        if (!empty($this->filters['level'])) {
            $query->where('level', $this->filters['level']);
        }

        if (!empty($this->filters['unit_kerja'])) {
            $query->where('unit_kerja', $this->filters['unit_kerja']);
        }

        if (!empty($this->filters['golongan'])) {
            $query->where('golongan', $this->filters['golongan']);
        }

        return $query->select($this->columns)->get();
    }

    public function headings(): array
    {
        return [
            'NIK',
            'NAMA',
            'JABATAN',
            'LEVEL',
            'UNIT KERJA',
            'GOLONGAN 2024',
            'TANGGAL DALAM JABATAN',
            'TMT UNIT KERJA',
            'TEMPAT LAHIR',
            'TANGGAL LAHIR',
            'TMT BEKERJA',
            'TANGGAL DIANGKAT STAF',
            'SUSUNAN KELUARGA',
            'JOB GRADER',
            'PERSON GRADE',
            'TANGGAL MBT',
            'TANGGAL PENSIUN',
            'AGAMA',
            'PENDIDIKAN TERAKHIR',
            'SEKOLAH',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1:T1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                ]);

                foreach (range('A', 'T') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
