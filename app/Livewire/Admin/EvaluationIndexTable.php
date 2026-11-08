<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Evaluation;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\QueryPipeline;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Manajemen Penilaian list (admin evaluations index) on the shared data table layer (WithDataTable).
 * Row actions (show, edit, delete) stay plain links and a CSRF-protected DELETE form handled by EvaluationController.
 */
class EvaluationIndexTable extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $kategori = '';

    #[Url(except: '')]
    public string $bidang = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    /** Assessment result group, driven by the KPI cards above the table (no toolbar field). */
    #[Url(except: '')]
    public string $hasil = '';

    public const HASIL_SANGAT_BAIK = 'sangat_baik';

    public const HASIL_BAIK = 'baik';

    public const HASIL_PERLU_PEMBINAAN = 'perlu_pembinaan';

    /**
     * Admin-only screen: route middleware is not re-applied to Livewire update requests, so refuse
     * non-admins on every request.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }

    /** "Total Penilaian" card: clears the result group the KPI cards drive, keeps search and panel filters. */
    public function clearKpiFilters(): void
    {
        $this->hasil = '';

        $this->resetPage($this->paginationName());
        $this->clearSelection();
    }

    /**
     * No-JS fallback link of a KPI card: the current table state with the given changes
     * (URL key => value, '' removes the key).
     *
     * @param  array<string, string>  $changes
     */
    public function kpiUrl(array $changes): string
    {
        $state = [
            'search' => $this->search,
            'kategori' => $this->kategori,
            'bidang' => $this->bidang,
            'from' => $this->from,
            'to' => $this->to,
            'hasil' => $this->hasil,
        ];

        return route('admin.evaluations.index', array_filter(array_merge($state, $changes), fn ($value) => $value !== ''));
    }

    /**
     * Page-level figures over every assessment (independent of search and filters, like the Daftar Karyawan cards):
     * the count per result group and the average weighted score.
     *
     * @return array{total: int, sangat_baik: int, baik: int, perlu_pembinaan: int, rata_rata: ?float}
     */
    public function kpi(): array
    {
        $row = Evaluation::query()
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when kategori_asesmen = ? then 1 else 0 end) as sangat_baik', ['Sangat Baik'])
            ->selectRaw('sum(case when kategori_asesmen = ? then 1 else 0 end) as baik', ['Baik'])
            ->selectRaw('sum(case when kategori_asesmen in (?, ?) then 1 else 0 end) as perlu_pembinaan', ['Cukup', 'Kurang'])
            ->selectRaw('avg(nilai_tertimbang) as rata_rata')
            ->first();

        return [
            'total' => (int) $row->total,
            'sangat_baik' => (int) $row->sangat_baik,
            'baik' => (int) $row->baik,
            'perlu_pembinaan' => (int) $row->perlu_pembinaan,
            'rata_rata' => $row->rata_rata === null ? null : (float) $row->rata_rata,
        ];
    }

    /**
     * Distinct non-empty 9-box categories, as [value => value] filter options.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function kategoriOptions(): array
    {
        return $this->distinctOptions('kategori_9box');
    }

    /**
     * Distinct non-empty task fields, as [value => value] filter options.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function bidangOptions(): array
    {
        return $this->distinctOptions('bidang_tugas');
    }

    protected function baseQuery(): Builder
    {
        return Evaluation::query()->with('employee');
    }

    protected function columns(): array
    {
        return [
            Column::make('pegawai', 'Karyawan')->searchable(function (Builder $q, string $term) {
                $q->where(function (Builder $group) use ($term) {
                    QueryPipeline::whereLikeAny($group, ['employee_nik'], $term);
                    $group->orWhereHas('employee', fn (Builder $employee) => QueryPipeline::whereLikeAny($employee, ['nama'], $term));
                });
            }),
            Column::make('jabatan_unit', 'Jabatan & Unit')->searchable(
                fn (Builder $q, string $term) => $q->whereHas('employee', fn (Builder $employee) => QueryPipeline::whereLikeAny($employee, ['jabatan', 'unit_kerja'], $term))
            ),
            Column::make('nilai_tertimbang', 'Nilai Tertimbang')->sortable()->mono(),
            Column::make('hasil_skor', 'Hasil Skor'),
            Column::make('kategori_9box', 'Kategori')->sortable(),
            Column::make('tanggal_pelaksanaan_asesmen', 'Periode Asesmen')->sortable(),
            Column::make('aksi', 'Aksi')->align('center'),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('kategori', 'Kategori 9-Box', $this->kategoriOptions)
                ->icon('fas fa-table-cells')
                ->allLabel('Semua Kategori')
                ->apply(fn (Builder $query, string $value) => $query->where('kategori_9box', $value)),
            Filter::select('bidang', 'Bidang Tugas', $this->bidangOptions)
                ->icon('fas fa-sitemap')
                ->apply(fn (Builder $query, string $value) => $query->where('bidang_tugas', $value)),
            Filter::dateFrom('from', 'Dari Tanggal', 'tanggal_pelaksanaan_asesmen'),
            Filter::dateTo('to', 'Sampai Tanggal', 'tanggal_pelaksanaan_asesmen'),
            // Hasil Penilaian, driven by the KPI cards above the table.
            Filter::select('hasil', 'Hasil Penilaian', [
                self::HASIL_SANGAT_BAIK => 'Sangat Baik',
                self::HASIL_BAIK => 'Baik',
                self::HASIL_PERLU_PEMBINAAN => 'Perlu Pembinaan',
            ])
                ->withoutControl()
                ->apply(fn (Builder $query, string $value) => match ($value) {
                    self::HASIL_SANGAT_BAIK => $query->where('kategori_asesmen', 'Sangat Baik'),
                    self::HASIL_BAIK => $query->where('kategori_asesmen', 'Baik'),
                    self::HASIL_PERLU_PEMBINAAN => $query->whereIn('kategori_asesmen', ['Cukup', 'Kurang']),
                }),
        ];
    }

    protected function defaultSort(): array
    {
        return ['tanggal_pelaksanaan_asesmen', 'desc'];
    }

    protected function tableLabel(): string
    {
        return 'Daftar Penilaian';
    }

    /** @return array<int, int> */
    protected function perPageOptions(): array
    {
        return [10, 20, 50, 100];
    }

    protected function defaultPerPage(): int
    {
        return 20;
    }

    protected function exportName(): ?string
    {
        return null;
    }

    public function render(): View
    {
        return view('livewire.admin.evaluation-index-table');
    }

    /** @return array<string, string> */
    private function distinctOptions(string $column): array
    {
        return Evaluation::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->mapWithKeys(fn ($value) => [(string) $value => (string) $value])
            ->all();
    }
}
