<?php

namespace App\Livewire\Admin\Employees;

use App\Livewire\Concerns\WithCollectionTable;
use App\Services\Import\ImportSessionStore;
use App\Support\HrisPermission;
use App\Support\NikMasker;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tabel pratinjau Impor Data Karyawan (tahap 2): baris valid per tab (semua, perubahan, baru, tetap) dan baris terkendala.
 *
 * Data tidak lagi dikirim utuh ke peramban: ringkasan pratinjau tersimpan terenkripsi di cache (ImportSessionStore), dan
 * komponen ini hanya membaca halaman yang tampil. Datanya berupa koleksi di memori, sehingga memakai WithCollectionTable
 * dan komponen bersama `x-admin.table.*` (ENGINEERING.md §2.3, DESIGN.md §8.4), bukan tabel Alpine buatan sendiri.
 * Sesi diambil dari token milik pengunggah: kepemilikan diperiksa pada setiap permintaan, dan token hanya dapat diganti
 * oleh peristiwa `import-preview-loaded` yang ikut divalidasi ulang di server. NIK disamarkan (UU PDP); pencarian tetap
 * dapat memakai NIK utuh, dan berkas koreksi memuat NIK lengkap untuk perbaikan.
 */
class ImportPreviewTable extends Component
{
    use WithCollectionTable;
    use WithPagination;

    /** Tab => judul tabel. Urutan mengikuti kartu KPI pada tahap 2. */
    public const TABS = [
        'all' => 'Semua Data Karyawan',
        'updated' => 'Data Perubahan Atribut',
        'created' => 'Data Karyawan Baru',
        'unchanged' => 'Data Tetap / Sudah Mutakhir',
        'errors' => 'Daftar Baris Terkendala',
    ];

    /** Tab valid => nilai `action` baris pratinjau. */
    private const ACTIONS = ['updated' => 'UPDATE', 'created' => 'CREATE', 'unchanged' => 'UNCHANGED'];

    private const SEARCH_MAX_LENGTH = 100;

    #[Locked]
    public string $token = '';

    public string $tab = 'all';

    public string $search = '';

    public int $perPage = 25;

    /** @var ?array<string, mixed> */
    private ?array $summaryCache = null;

    /** Rute middleware tidak diterapkan ulang pada permintaan Livewire: izin impor diperiksa pada setiap permintaan. */
    public function boot(): void
    {
        abort_unless(Gate::allows(HrisPermission::KARYAWAN_IMPORT), 403, 'Unauthorized.');
    }

    public function mount(): void
    {
        $this->perPage = $this->defaultPerPage();
    }

    /** Dikirim oleh wizard saat pratinjau selesai (atau saat direset dengan token kosong). Token divalidasi ulang di server. */
    #[On('import-preview-loaded')]
    public function load(string $token = '', string $tab = 'all'): void
    {
        $this->token = Str::isUuid($token) && $this->ownedSummary($token) !== null ? $token : '';
        $this->summaryCache = null;
        $this->tab = $this->validTab($tab);
        $this->search = '';
        $this->resetPage();
    }

    /** Dikirim wizard saat kartu KPI dipilih. */
    #[On('import-preview-tab')]
    public function setTab(string $tab = 'all'): void
    {
        $this->tab = $this->validTab($tab);
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr(trim($this->search), 0, self::SEARCH_MAX_LENGTH);
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function render(): View
    {
        // Nilai yang dapat diubah klien divalidasi ulang pada setiap render.
        $this->tab = $this->validTab($this->tab);
        $this->perPage = $this->normalisedPerPage();

        $summary = $this->summary();
        $rows = $this->paginateCollection($summary === null ? collect() : $this->filtered($summary));

        return view('livewire.admin.employees.import-preview-table', [
            'rows' => $rows,
            'loaded' => $summary !== null,
            'title' => self::TABS[$this->tab],
            'isErrors' => $this->tab === 'errors',
            'failedCount' => (int) ($summary['failed_count'] ?? 0),
            'perPageOptions' => $this->perPageOptions(),
        ]);
    }

    /** NIK samaran untuk tampilan; nilai bukan NIK (mis. "Unknown" pada baris tanpa NIK) ditampilkan apa adanya. */
    public function maskedNik(mixed $nik): string
    {
        $nik = trim((string) $nik);

        return $nik === '' ? '-' : (ctype_digit($nik) ? NikMasker::mask($nik) : $nik);
    }

    private function validTab(string $tab): string
    {
        return array_key_exists($tab, self::TABS) ? $tab : 'all';
    }

    /** @return array<string, mixed>|null ringkasan pratinjau milik pengguna ini, atau null bila belum ada atau sesi berakhir */
    private function summary(): ?array
    {
        if ($this->token === '') {
            return null;
        }

        return $this->summaryCache ??= $this->ownedSummary($this->token);
    }

    /** @return array<string, mixed>|null */
    private function ownedSummary(string $token): ?array
    {
        $sessions = app(ImportSessionStore::class);

        if (! $sessions->isOwnedBy($token, auth()->user())) {
            return null;
        }

        return $sessions->preview($token)['summary'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return Collection<int, array<string, mixed>>
     */
    private function filtered(array $summary): Collection
    {
        $rows = $this->tab === 'errors'
            ? collect($summary['errors'] ?? [])
            : collect($summary['sample_rows'] ?? [])->when(
                isset(self::ACTIONS[$this->tab]),
                fn (Collection $all) => $all->where('action', self::ACTIONS[$this->tab])
            );

        $needle = Str::lower($this->search);
        if ($needle === '') {
            return $rows->values();
        }

        return $rows->filter(function (array $row) use ($needle): bool {
            $errors = is_array($row['errors'] ?? null) ? implode(' ', $row['errors']) : (string) ($row['errors'] ?? '');
            $haystack = Str::lower(implode(' ', [
                $row['row'] ?? '', $row['nik'] ?? '', $row['nama'] ?? '', $row['jabatan'] ?? '', $row['unit_kerja'] ?? '', $row['golongan'] ?? '', $errors,
            ]));

            return str_contains($haystack, $needle);
        })->values();
    }
}
