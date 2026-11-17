<?php

namespace App\Livewire\Admin\Master;

use App\Exports\FormasiExport;
use App\Models\MasterAuditLog;
use App\Models\StructureDecree;
use App\Services\Structure\StructureAsOfService;
use App\Services\Structure\StructureDiff;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Struktur Organisasi per Tanggal (DOMAIN.md §3.7): versi struktur menurut SK yang berlaku pada tanggal pilihan beserta
 * pemangku jabatannya dari riwayat jabatan. Baca-saja; NIK selalu tersamar dan setiap ekspor tercatat pada log audit.
 */
class StructureAsOfPage extends Component
{
    use WithPagination;

    private const SEARCH_MAX_LENGTH = 100;

    /** Tanggal struktur (Y-m-d). Kosong atau tidak valid kembali ke hari ini. */
    #[Url(except: '')]
    public string $date = '';

    #[Url(except: '')]
    public string $search = '';

    /** Tampilkan ringkasan selisih versi pada tanggal itu dengan struktur sekarang. */
    #[Url(except: false)]
    public bool $compare = false;

    public int $perPage = 25;

    /** Rute middleware tidak diterapkan ulang pada permintaan update Livewire, jadi tolak non-admin di setiap permintaan. */
    public function boot(): void
    {
        abort_unless(Gate::allows('struktur.prepare'), 403, 'Unauthorized.');
    }

    public function mount(): void
    {
        $this->perPage = $this->defaultPerPage();
        $this->normaliseDate();
        $this->normaliseSearch();
    }

    public function updatedDate(): void
    {
        $this->normaliseDate();
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->normaliseSearch();
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, $this->perPageOptions(), true)) {
            $this->perPage = $this->defaultPerPage();
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    /** @return array{decree: StructureDecree, rows: list<array<string, mixed>>, outside_structure: int}|null */
    #[Computed]
    public function report(): ?array
    {
        return app(StructureAsOfService::class)->report(Carbon::parse($this->date));
    }

    #[Computed]
    public function diff(): ?StructureDiff
    {
        return $this->compare ? app(StructureAsOfService::class)->compareWithCurrent(Carbon::parse($this->date)) : null;
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function filteredRows(): Collection
    {
        $term = mb_strtolower(trim($this->search));
        $rows = collect($this->report['rows'] ?? []);

        if ($term === '') {
            return $rows;
        }

        return $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['unit'].' '.$row['jabatan'].' '.$row['wilayah']), $term))->values();
    }

    #[Computed]
    public function pagedRows(): LengthAwarePaginator
    {
        $rows = $this->filteredRows;
        $perPage = in_array($this->perPage, $this->perPageOptions(), true) ? $this->perPage : $this->defaultPerPage();
        $page = max(1, min($this->getPage(), (int) ceil($rows->count() / $perPage)));

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);
    }

    public function export(): BinaryFileResponse
    {
        abort_unless(Gate::allows('struktur.prepare'), 403, 'Unauthorized.');

        $report = $this->report;
        abort_if($report === null, 404);

        $rows = $this->filteredRows->values()->map(fn (array $row, int $i): array => [
            $i + 1,
            $row['unit'],
            $row['wilayah'] ?? '-',
            $row['is_active'] ? 'Aktif' : 'Nonaktif',
            $row['jabatan'],
            $row['rm_level'] ?? '-',
            $row['bidang'] ?? '-',
            $row['kuota'],
            $row['holder_count'],
            collect($row['holders'])->map(fn (array $holder): string => $holder['nama'].' ('.$holder['nik_masked'].')')->implode('; '),
        ]);

        // Log audit hanya memuat versi dan cakupan ekspor, tidak memuat data pribadi pemangku.
        MasterAuditLog::record(auth()->user(), 'structure_as_of.export', 'structure_as_of', null, 'Struktur per Tanggal', null, [
            'date' => $this->date,
            'sk_number' => $report['decree']->sk_number,
            'rows' => $rows->count(),
            'search' => $this->search !== '' ? $this->search : null,
        ]);

        $export = new FormasiExport('Struktur per Tanggal', ['NO', 'UNIT KERJA', 'WILAYAH', 'STATUS UNIT', 'JABATAN', 'RM BAND', 'BIDANG', 'KUOTA', 'TERISI', 'PEMANGKU'], $rows, auth()->user());

        return Excel::download($export, 'Struktur_per_Tanggal_'.str_replace('-', '', $this->date).'_PTPN4_Reg5_'.now()->format('Ymd_His').'.xlsx');
    }

    public function render(): View
    {
        return view('livewire.admin.master.structure-as-of-page', [
            'perPageOptions' => $this->perPageOptions(),
        ]);
    }

    private function normaliseDate(): void
    {
        $valid = false;

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1) {
            try {
                $valid = Carbon::createFromFormat('!Y-m-d', $this->date)->format('Y-m-d') === $this->date;
            } catch (\Throwable) {
                // Tanggal mustahil (mis. 2026-02-31): jatuh ke hari ini.
            }
        }

        $this->date = $valid ? $this->date : now()->toDateString();
    }

    private function normaliseSearch(): void
    {
        $this->search = mb_substr(trim($this->search), 0, self::SEARCH_MAX_LENGTH);
    }

    /** @return array<int, int> */
    private function perPageOptions(): array
    {
        return array_map('intval', config('hris.pagination.options'));
    }

    private function defaultPerPage(): int
    {
        return (int) config('hris.pagination.default');
    }
}
