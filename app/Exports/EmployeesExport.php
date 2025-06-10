<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeesExport implements FromCollection, WithHeadings
{
    protected $filters;

    // Define the columns to export
    protected $columns = [
        'nik',
        'nama',
        'jabatan',
        'level',
        'unit_kerja',
        'golongan_2024',
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

        if (!empty($this->filters['golongan_2024'])) {
            $query->where('golongan_2024', $this->filters['golongan_2024']);
        }

        return $query->select($this->columns)->get();
    }

    public function headings(): array
    {
        return [
            'NIK',
            'Nama',
            'Jabatan',
            'Level',
            'Unit Kerja',
            'Golongan 2024',
            'Tanggal Dalam Jabatan',
            'TMT Unit Kerja',
            'Tempat Lahir',
            'Tanggal Lahir',
            'TMT Bekerja',
            'Tanggal Diangkat Staf',
            'Susunan Keluarga',
            'Job Grader',
            'Person Grade',
            'Tanggal MBT',
            'Tanggal Pensiun',
            'Agama',
            'Pendidikan Terakhir',
            'Sekolah',
        ];
    }
}
