<?php

namespace App\Livewire\Admin\Master;

use App\Exports\FormasiExport;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\StructureDecree;
use App\Services\Structure\StructureAsOfService;
use App\Services\Structure\StructureDiff;
use App\Support\DataTable\Filter;
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
 * dipakai menelusuri struktur dan pemangku jabatan di masa lalu (audit, pemeriksaan): satu kendali waktu (versi atau tanggal),
 * tabel per jabatan atau per unit, perbandingan antarversi, dan riwayat jabatan karyawan. NIK selalu tersamar dan setiap
 * ekspor tercatat pada log audit.
 */
class StructureVersionsPage extends Component
{
    use WithPagination;

    public const TABS = ['struktur', 'karyawan'];

    public const VIEWS = ['jabatan', 'unit'];

    public const FILL_STATES = ['terisi' => 'Terisi', 'kosong' => 'Kosong', 'melebihi' => 'Melebihi kuota'];

    private const SEARCH_MAX_LENGTH = 100;

    /** Tab: struktur (tabel versi) atau karyawan (riwayat jabatan seorang karyawan). */
    #[Url(except: 'struktur')]
    public string $tab = 'struktur';

    /** Tanggal yang dilihat (Y-m-d). Kosong atau tidak valid kembali ke hari ini. */
    #[Url(except: '')]
    public string $date = '';

    /** Id SK dari versi yang berlaku pada tanggal itu ('' bila sebelum SK pertama). Memilihnya memindahkan tanggal. */
    public string $version = '';

    /** Pembanding: '' (tidak ada), 'current' (struktur sekarang), atau id SK versi lain. */
    #[Url(except: '')]
    public string $compare = '';

    /** Tampilan tabel: jabatan (datar, bawaan) atau unit (dikelompokkan, dapat dibuka). */
    #[Url(except: 'jabatan')]
    public string $view = 'jabatan';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $wilayahFilter = '';

    #[Url(except: '')]
    public string $rmFilter = '';

    #[Url(except: '')]
    public string $bidangFilter = '';

    #[Url(except: '')]
    public string $isiFilter = '';

    public int $perPage = 25;

    /** Rute middleware tidak diterapkan ulang pada permintaan update Livewire, jadi tolak non-admin di setiap permintaan. */
    public function boot(): void
    {
        abort_unless(Gate::allows('struktur.prepare'), 403, 'Unauthorized.');
    }

    public function mount(): void
    {
        $this->perPage = $this->defaultPerPage();
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'struktur';
        $this->view = in_array($this->view, self::VIEWS, true) ? $this->view : 'jabatan';
        $this->normaliseDate();
        $this->normaliseSearch();
        $this->syncVersion();
        $this->normaliseCompare();
        $this->normaliseFilters();
    }

    /** Memilih versi memindahkan tanggal: versi yang sedang berlaku membuka hari ini, versi lampau hari pertama berlakunya. */
    public function updatedVersion(): void
    {
        $chosen = collect($this->versions)->first(fn (array $v): bool => (string) $v['decree']->id === $this->version);

        if ($chosen !== null) {
            $this->date = $chosen['is_current'] ? now()->toDateString() : $chosen['from']->toDateString();
        }

        $this->afterTimeChange();
    }

    public function updatedDate(): void
    {
        $this->normaliseDate();
        $this->afterTimeChange();
    }

    public function updatedCompare(): void
    {
        $this->normaliseCompare();
    }

    public function updatedTab(): void
    {
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'struktur';
    }

    public function updatedView(): void
    {
        $this->view = in_array($this->view, self::VIEWS, true) ? $this->view : 'jabatan';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->normaliseSearch();
        $this->resetPage();
    }

    public function updatedWilayahFilter(): void
    {
        $this->normaliseFilters();
        $this->resetPage();
    }

    public function updatedRmFilter(): void
    {
        $this->normaliseFilters();
        $this->resetPage();
    }

    public function updatedBidangFilter(): void
    {
        $this->normaliseFilters();
        $this->resetPage();
    }

    public function updatedIsiFilter(): void
    {
        $this->normaliseFilters();
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
        $this->wilayahFilter = $this->rmFilter = $this->bidangFilter = $this->isiFilter = '';
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

    /** Pilihan pembanding: struktur sekarang dan setiap versi lain (bukan yang sedang dilihat). @return list<array{value: string, label: string}> */
    #[Computed]
    public function compareOptions(): array
    {
        $selectedId = $this->report['decree']->id ?? null;
        $options = [['value' => 'current', 'label' => 'Struktur sekarang']];

        foreach ($this->versions as $version) {
            if ($version['decree']->id !== $selectedId) {
                $options[] = ['value' => (string) $version['decree']->id, 'label' => $version['decree']->sk_number];
            }
        }

        return $options;
    }

    /** Label pembanding yang dipilih, untuk judul bagian selisih. */
    #[Computed]
    public function compareLabel(): ?string
    {
        foreach ($this->compareOptions as $option) {
            if ($option['value'] === $this->compare) {
                return $option['value'] === 'current' ? 'struktur sekarang' : $option['label'];
            }
        }

        return null;
    }

    #[Computed]
    public function diff(): ?StructureDiff
    {
        $service = app(StructureAsOfService::class);
        $selected = $this->report['decree'] ?? null;

        if ($this->compare === '' || $selected === null) {
            return null;
        }

        if ($this->compare === 'current') {
            return $service->compareWithCurrent(Carbon::parse($this->date));
        }

        $other = collect($this->versions)->first(fn (array $v): bool => (string) $v['decree']->id === $this->compare)['decree'] ?? null;

        return $other === null ? null : $service->compareVersions($selected, $other);
    }

    /** Baris jabatan (datar) yang lolos pencarian dan filter. @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function filteredRows(): Collection
    {
        $term = mb_strtolower(trim($this->search));

        return collect($this->report['rows'] ?? [])->filter(function (array $row) use ($term): bool {
            if ($this->wilayahFilter !== '' && ($row['wilayah'] ?? '') !== $this->wilayahFilter) {
                return false;
            }
            if ($this->rmFilter !== '' && ($row['rm_level'] ?? '') !== $this->rmFilter) {
                return false;
            }
            if ($this->bidangFilter !== '' && ($row['bidang'] ?? '') !== $this->bidangFilter) {
                return false;
            }
            if ($this->isiFilter === 'terisi' && $row['holder_count'] === 0) {
                return false;
            }
            if ($this->isiFilter === 'kosong' && $row['holder_count'] !== 0) {
                return false;
            }
            if ($this->isiFilter === 'melebihi' && $row['holder_count'] <= $row['kuota']) {
                return false;
            }

            if ($term === '') {
                return true;
            }

            $haystack = $row['unit'].' '.($row['wilayah'] ?? '').' '.$row['jabatan'].' '.implode(' ', array_column($row['holders'], 'nama'));

            return str_contains(mb_strtolower($haystack), $term);
        })->values();
    }

    /**
     * Unit untuk tampilan per unit. Tanpa pencarian dan filter memakai seluruh unit versi itu (termasuk yang tanpa formasi);
     * selain itu hanya unit yang masih memiliki jabatan cocok, dengan jabatan yang cocok saja.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function groupedUnits(): Collection
    {
        if (! $this->hasActiveFilters()) {
            return collect($this->report['units'] ?? []);
        }

        return $this->filteredRows->groupBy('unit')->map(fn (Collection $seats, string $unit): array => [
            'unit' => $unit,
            'wilayah' => $seats->first()['wilayah'],
            'is_active' => $seats->first()['is_active'],
            'seats' => $seats->all(),
            'seat_count' => $seats->count(),
            'kuota' => $seats->sum('kuota'),
            'filled' => $seats->sum('holder_count'),
        ])->values();
    }

    #[Computed]
    public function pagedRows(): LengthAwarePaginator
    {
        return $this->paginate($this->view === 'unit' ? $this->groupedUnits : $this->filteredRows);
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->activeFilterValues() !== [];
    }

    /** @return array<string, string> [properti => nilai] filter yang sedang aktif. */
    public function activeFilterValues(): array
    {
        return array_filter([
            'wilayahFilter' => $this->wilayahFilter,
            'rmFilter' => $this->rmFilter,
            'bidangFilter' => $this->bidangFilter,
            'isiFilter' => $this->isiFilter,
        ], fn (string $value): bool => $value !== '');
    }

    /** @return array<int, Filter> */
    public function filters(): array
    {
        $rows = collect($this->report['rows'] ?? []);
        $distinct = fn (string $key): array => $rows->pluck($key)->filter()->unique()->sort()->values()->all();

        $wilayah = $distinct('wilayah');
        $rm = $distinct('rm_level');
        $bidang = $distinct('bidang');

        return [
            Filter::select('wilayahFilter', 'Wilayah', array_combine($wilayah, $wilayah))->icon('fas fa-location-dot'),
            Filter::select('rmFilter', 'RM Band', array_combine($rm, $rm))->icon('fas fa-layer-group'),
            Filter::select('bidangFilter', 'Bidang', array_combine($bidang, array_map(fn (string $code): string => Position::BIDANG_LIST[$code] ?? $code, $bidang)))->icon('fas fa-sitemap'),
            Filter::select('isiFilter', 'Keterisian', self::FILL_STATES)->icon('fas fa-user-check'),
        ];
    }

    public function export(): BinaryFileResponse
    {
        abort_unless(Gate::allows('struktur.prepare'), 403, 'Unauthorized.');

        $report = $this->report;
        abort_if($report === null, 404);

        $rows = $this->filteredRows->map(fn (array $row, int $i): array => [
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
            'posisi_per' => $this->date,
            'nomor_sk' => $report['decree']->sk_number,
            'jumlah_baris' => $rows->count(),
            'pencarian' => $this->search !== '' ? $this->search : null,
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

    /** @param Collection<int, mixed> $items */
    private function paginate(Collection $items): LengthAwarePaginator
    {
        $perPage = in_array($this->perPage, $this->perPageOptions(), true) ? $this->perPage : $this->defaultPerPage();
        $page = max(1, min($this->getPage(), (int) ceil($items->count() / $perPage)));

        return new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page);
    }

    private function afterTimeChange(): void
    {
        $this->syncVersion();
        $this->normaliseCompare();
        $this->normaliseFilters();
        $this->resetPage();
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

    /** Menyelaraskan pilihan versi dengan tanggal: versi yang berlaku pada tanggal itu, atau kosong bila belum ada. */
    private function syncVersion(): void
    {
        $decree = app(StructureAsOfService::class)->decreeFor(Carbon::parse($this->date));
        $this->version = $decree === null ? '' : (string) $decree->id;
    }

    private function normaliseCompare(): void
    {
        $allowed = array_column($this->compareOptions, 'value');
        $this->compare = in_array($this->compare, $allowed, true) ? $this->compare : '';
    }

    private function normaliseFilters(): void
    {
        foreach ($this->filters() as $filter) {
            $property = $filter->property;
            if ($this->{$property} !== '' && ! $filter->allows($this->{$property})) {
                $this->{$property} = '';
            }
        }
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
