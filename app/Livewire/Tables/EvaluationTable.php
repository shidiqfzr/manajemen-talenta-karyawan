<?php

namespace App\Livewire\Tables;

use App\Models\Evaluation;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Carbon\Carbon;

class EvaluationTable extends DataTableComponent
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
        return Evaluation::query()
            ->where('employee_nik', $this->employeeId)
            ->select('evaluations.*')
            ->orderByDesc('tanggal_pelaksanaan_asesmen');
    }

    public function columns(): array
    {
        return [
            Column::make('Periode', 'tanggal_pelaksanaan_asesmen')
                ->format(fn($value, $row) => $this->formatPeriode($row))
                ->html()
                ->sortable(),

            Column::make('Nilai Tertimbang', 'nilai_tertimbang')
                ->format(fn($value, $row) => is_null($value) ? '-' : number_format($value, 2))
                ->sortable(),

            Column::make('9-Box', 'kategori_9box')
                ->format(fn($value, $row) => $this->formatNineBox($row))
                ->html(),

            Column::make('Bidang Tugas', 'bidang_tugas')
                ->sortable()
                ->searchable(),

            Column::make('Asesmen', 'lembaga_asesmen')
                ->format(fn($value, $row) => $this->formatAsesmen($row))
                ->deselected()
                ->html(),

            Column::make('Masa Berlaku', 'expired_asesmen')
                ->format(fn($value, $row) => $this->formatDate($value))
                ->deselected()
                ->sortable(),

            Column::make('Kepemimpinan', 'nilai_kepemimpinan')
                ->deselected(),

            Column::make('Perilaku Budaya', 'nilai_perilaku_budaya')
                ->deselected(),

            Column::make('Pengalaman Teknis', 'nilai_pengalaman_teknis')
                ->deselected(),

            Column::make('Kematangan Pribadi', 'nilai_kematangan_pribadi')
                ->deselected(),

            Column::make('Hasil Skor', 'hasil_skor_asesmen')
                ->deselected(),

            Column::make('Kategori Asesmen', 'kategori_asesmen')
                ->deselected(),

            Column::make('Keterangan', 'keterangan_asesmen')
                ->deselected(),

            Column::make('Aksi', 'id')
                ->format(fn($value, $row) => view('admin.employees.partials.evaluation-actions')->with('row', $row)->render())
                ->html()
                ->excludeFromColumnSelect(),
        ];
    }

    protected function formatPeriode(Evaluation $row): string
    {
        return $this->formatDate($row->tanggal_pelaksanaan_asesmen) .
            ' &ndash; ' .
            $this->formatDate($row->expired_asesmen);
    }

    protected function formatDate($value): string
    {
        if (empty($value)) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    protected function formatNineBox(Evaluation $row): string
    {
        $smkbk = is_null($row->skor_smkbk_9box) ? '0.00' : number_format($row->skor_smkbk_9box, 2);
        $cli = is_null($row->skor_cli_9box) ? '0.00' : number_format($row->skor_cli_9box, 2);
        $badge = $row->kategori_9box ? "<span class=\"ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-600 text-white\">{$row->kategori_9box}</span>" : '';

        return "SMKBK {$smkbk} / CLI {$cli} {$badge}";
    }

    protected function formatAsesmen(Evaluation $row): string
    {
        $label = $row->lembaga_asesmen ?? '-';
        $date = $row->tanggal_pelaksanaan_asesmen ? ' <span class="text-gray-500">(' . Carbon::parse($row->tanggal_pelaksanaan_asesmen)->format('d M Y') . ')</span>' : '';
        return "{$label}{$date}";
    }

    public function deleteRow(int $id): void
    {
        $record = Evaluation::where('employee_nik', $this->employeeId)->findOrFail($id);
        $record->delete();

        session()->flash('message', 'Data evaluasi dihapus.');
    }
}
