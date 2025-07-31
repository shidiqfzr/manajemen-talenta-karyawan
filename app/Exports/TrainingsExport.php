<?php

namespace App\Exports;

use App\Models\Training;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class TrainingsExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    protected $filters;

    protected $columns = [
        'judul',
        'jenis',
        'metode',
        'tanggal_mulai',
        'tanggal_akhir',
        'biaya_tiket_peserta',
        'biaya_pelatihan',
        'penyelenggara',
        'nomor_surat',
        'tanggal_surat',
        'keterangan',
        'bidang_pelatihan',
        'jam_belajar_per_hari',
        'jumlah_man_hours',
    ];

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Training::withCount('employees'); 

        if (!empty($this->filters['start_date'])) {
            $query->whereDate('tanggal_mulai', '>=', $this->filters['start_date']);
        }

        if (!empty($this->filters['end_date'])) {
            $query->whereDate('tanggal_mulai', '<=', $this->filters['end_date']);
        }

        return $query->get();
    }

    public function map($training): array
    {
        return [
            $training->judul,
            $training->jenis,
            $training->metode,
            $training->tanggal_mulai,
            $training->tanggal_akhir,
            $training->biaya_tiket_peserta,
            $training->biaya_pelatihan,
            $training->penyelenggara,
            $training->nomor_surat,
            $training->tanggal_surat,
            $training->keterangan,
            $training->bidang_pelatihan,
            $training->jam_belajar_per_hari,
            $training->jumlah_man_hours,
            $training->employees_count, 
        ];
    }

    public function headings(): array
    {
        return [
            'JUDUL',
            'JENIS',
            'METODE',
            'TANGGAL MULAI',
            'TANGGAL AKHIR',
            'BIAYA TIKET PESERTA',
            'BIAYA PELATIHAN',
            'PENYELENGGARA',
            'NOMOR SURAT',
            'TANGGAL SURAT',
            'KETERANGAN',
            'BIDANG PELATIHAN',
            'JAM BELAJAR PER HARI',
            'JUMLAH MAN HOURS',
            'JUMLAH PESERTA', 
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Bold header row
                $sheet->getStyle('A1:O1')->applyFromArray([
                    'font' => ['bold' => true],
                ]);

                // Auto-size all columns (A to O)
                foreach (range('A', 'O') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
