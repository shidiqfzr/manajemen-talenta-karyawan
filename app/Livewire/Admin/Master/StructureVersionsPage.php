<?php

namespace App\Livewire\Admin\Master;

use App\Exports\FormasiExport;
use App\Models\MasterAuditLog;
use App\Models\StructureDecree;
use App\Services\Structure\StructureAsOfService;
use App\Services\Structure\StructureDiff;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
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
 * Versi Struktur (DOMAIN.md §3.7): setiap SK yang diterapkan menjadi satu versi struktur organisasi. Layar baca-saja ini
 * menampilkan daftar versi, lalu struktur beserta pemangku jabatan pada versi atau tanggal yang dipilih (untuk penelusuran
 * dan audit). NIK selalu tersamar dan setiap ekspor tercatat pada log audit.
 */
class StructureVersionsPage extends Component
{
    use WithPagination;

    private const SEARCH_MAX_LENGTH = 100;

    /** Tanggal yang dilihat (Y-m-d). Kosong atau tidak valid kembali ke hari ini. */
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

    /** Pilih versi: versi yang sedang berlaku membuka hari ini, versi lampau membuka hari pertama berlakunya. */
    public function selectVersion(int $decreeId): void
    {
        $version = collect($this->versions)->first(fn (array $v): bool => $v['decree']->id === $decreeId);
        abort_if($version === null, 404);

        $this->date = $version['is_current'] ? now()->toDateString() : $version['from']->toDateString();
        $this->resetPage();
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

    /** @return list<array{decree: StructureDecree, from: CarbonImmutable, to: ?CarbonImmutable, is_current: bool, units: int, seats: int, kuota: int}> */
    #[Computed]
    public function versions(): array
    {
        return app(StructureAsOfService::class)->versions();
    }

    /** @return array<string, mixed>|null */
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

    /** Unit yang cocok dengan pencarian: nama unit, wilayah, atau salah satu jabatannya. @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function filteredUnits(): Collection
    {
        $term = mb_strtolower(trim($this->search));
        $units = collect($this->report['units'] ?? []);

        if ($term === '') {
            return $units;
        }

        return $units->filter(fn (array $unit): bool => str_contains(mb_strtolower($unit['unit'].' '.$unit['wilayah']), $term)
            || collect($unit['seats'])->contains(fn (array $seat): bool => str_contains(mb_strtolower($seat['jabatan']), $term)))->values();
    }

    #[Computed]
    public function pagedUnits(): LengthAwarePaginator
    {
        $units = $this->filteredUnits;
        $perPage = in_array($this->perPage, $this->perPageOptions(), true) ? $this->perPage : $this->defaultPerPage();
        $page = max(1, min($this->getPage(), (int) ceil($units->count() / $perPage)));

        return new LengthAwarePaginator($units->forPage($page, $perPage)->values(), $units->count(), $perPage, $page);
    }

    public function export(): BinaryFileResponse
    {
        abort_unless(Gate::allows('struktur.prepare'), 403, 'Unauthorized.');

        $report = $this->report;
        abort_if($report === null, 404);

        $rows = $this->filteredUnits
            ->flatMap(fn (array $unit): array => array_map(fn (array $seat): array => $seat + ['is_active' => $unit['is_active']], $unit['seats']))
            ->values()
            ->map(fn (array $row, int $i): array => [
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
        MasterAuditLog::record(auth()->user(), 'structure_as_of.export', 'structure_as_of', null, 'Versi Struktur', null, [
            'date' => $this->date,
            'sk_number' => $report['decree']->sk_number,
            'rows' => $rows->count(),
            'search' => $this->search !== '' ? $this->search : null,
        ]);

        $export = new FormasiExport('Versi Struktur', ['NO', 'UNIT KERJA', 'WILAYAH', 'STATUS UNIT', 'JABATAN', 'RM BAND', 'BIDANG', 'KUOTA', 'TERISI', 'PEMANGKU'], $rows, auth()->user());

        return Excel::download($export, 'Versi_Struktur_'.str_replace('-', '', $this->date).'_PTPN4_Reg5_'.now()->format('Ymd_His').'.xlsx');
    }

    public function render(): View
    {
        return view('livewire.admin.master.structure-versions-page', [
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
