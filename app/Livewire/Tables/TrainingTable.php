<?php

namespace App\Livewire\Tables;

use App\Models\Training;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class TrainingTable extends DataTableComponent
{
    public string $employeeId;

    public function mount(string $employeeId): void
    {
        $this->employeeId = $employeeId;
    }

    public function configure(): void
    {
        $this->setPerPageAccepted([5, 10, 25]);
        $this->setPerPage(5);
        $this->setPrimaryKey('id');
        $this->setColumnSelectStatus(true);

        $this->setTrAttributes(fn($row) => [
            'class' => 'hover:bg-gray-50',
        ]);
    }

    public function builder(): Builder
    {
        // Load only trainings attached to this employee and eager-load the relationship
        return Training::query()
            ->whereHas('employees', fn($q) => $q->where('employee_nik', $this->employeeId))
            ->with(['employees' => fn($q) => $q->where('employee_nik', $this->employeeId)])
            ->select('trainings.*')
            ->orderByDesc('tanggal_mulai');
    }

    public function columns(): array
    {
        return [
            Column::make('Judul Pelatihan', 'judul')
                ->sortable()
                ->searchable()
                ->format(fn($value, $row) => $row->judul),

            Column::make('Tanggal', 'tanggal_mulai')
                ->format(fn($value, $row) => $this->formatPeriode($row))
                ->html(),

            Column::make('Penyelenggara', 'penyelenggara')
                ->sortable()
                ->searchable(),

            Column::make('Sertifikat', 'id')
                ->format(fn($value, $row) => $this->formatSertifikat($row))
                ->html(),

            Column::make('Jenis', 'jenis')
                ->sortable()
                ->searchable()
                ->deselected(),

            Column::make('Metode', 'metode')
                ->sortable()
                ->searchable()
                ->deselected(),

            Column::make('Bidang', 'bidang_pelatihan')
                ->sortable()
                ->searchable()
                ->deselected(),

            Column::make('Jam / Hari', 'jam_belajar_per_hari')
                ->deselected(),

            Column::make('Man Hours', 'jumlah_man_hours')
                ->deselected(),

            Column::make('Nomor Surat', 'nomor_surat')
                ->deselected(),

            Column::make('Tanggal Surat', 'tanggal_surat')
                ->format(fn($value, $row) => $row->tanggal_surat ? \Carbon\Carbon::parse($row->tanggal_surat)->format('d M Y') : '-')
                ->deselected(),

            Column::make('Aksi', 'id')
                ->format(fn($value, $row) => view('admin.employees.partials.training-actions')->with('row', $row)->render())
                ->html()
                ->excludeFromColumnSelect(),
        ];
    }

    protected function formatPeriode(Training $row): string
    {
        $start = $row->tanggal_mulai?->format('d M Y') ?? '-';
        $end = $row->tanggal_akhir?->format('d M Y') ?? '-';
        return "{$start} &ndash; {$end}";
    }

    protected function formatSertifikat(Training $row): string
    {
        // The relation was eager-loaded filtered to this employee, so pick first employee pivot
        $emp = $row->employees->first();
        $sertifikat = $emp?->pivot?->sertifikat;

        if ($sertifikat) {
            $url = asset('storage/' . $sertifikat);
            return <<<HTML
                <a href="{$url}" target="_blank"
                   class="inline-flex items-center px-3 py-1.5 bg-green-100 text-green-700 text-xs font-medium rounded-full hover:bg-green-200 transition-colors duration-150"
                   title="Unduh Sertifikat">
                   <i class="fas fa-download mr-1"></i>Unduh
                </a>
            HTML;
        }

        return <<<HTML
            <span class="inline-flex items-center px-3 py-1.5 bg-yellow-100 text-amber-700 text-xs font-medium rounded-full">
                <i class="fas fa-exclamation-triangle mr-1"></i>Tidak Ada
            </span>
        HTML;
    }

    public function detachTraining(int $id): void
    {
        // detach pivot for this employee
        $training = Training::findOrFail($id);
        $training->employees()->detach($this->employeeId);

        session()->flash('message', 'Pelatihan berhasil dihapus dari karyawan.');
    }
}
