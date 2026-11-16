<?php

namespace App\Livewire\Admin\Master;

use App\Exceptions\FormasiRuleViolationException;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use App\Services\Formasi\MasterFormasiService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tab "Jabatan Penampungan" (DOMAIN.md §3.6): daftar jabatan Advisor dan Diperbantukan, pembukaannya pada unit, dan
 * bezetting per unit. Jabatan penampungan opsional pada sebuah unit dan bukan bagian Master Formasi: tanpa kuota, tidak
 * mengisi atau mengosongkan formasi mana pun. Seluruh perubahan lewat MasterFormasiService (transaksi + riwayat perubahan);
 * komponen ini hanya mengurus dialog, filter, dan tampilan.
 */
class PoolPositions extends Component
{
    use WithPagination;

    public const VIEW_JABATAN = 'jabatan';

    public const VIEW_UNIT = 'unit';

    public const JENIS_LABELS = ['advisor' => 'Advisor', 'diperbantukan' => 'Diperbantukan'];

    private const SEARCH_MAX_LENGTH = 100;

    #[Url(as: 'tampilan', except: self::VIEW_JABATAN)]
    public string $view = self::VIEW_JABATAN;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    // Dialog Tambah Jabatan Penampungan
    public bool $creating = false;

    public string $newNama = '';

    public string $newJenis = '';

    public string $newRm = '';

    // Dialog Buka pada Unit
    #[Locked]
    public ?int $openingPositionId = null;

    public ?int $openUnitId = null;

    // Dialog Tutup pada Unit
    #[Locked]
    public ?int $closePositionId = null;

    #[Locked]
    public ?int $closeUnitId = null;

    public string $closeReason = '';

    // Dialog Ubah Nama
    #[Locked]
    public ?int $renamePositionId = null;

    public string $renameNama = '';

    public string $renameReason = '';

    // Dialog Hapus Jabatan
    #[Locked]
    public ?int $deletePositionId = null;

    public string $deleteReason = '';

    public ?string $notice = null;

    /** Data jabatan dan bezetting, dimuat sekali per permintaan lalu dibuang setelah setiap perubahan. */
    private ?array $cache = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->normalise();
    }

    public function updatedSearch(): void
    {
        $this->normalise();
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->normalise();
        $this->resetPage();
    }

    public function setView(string $view): void
    {
        $this->authorizeAdmin();

        if (in_array($view, [self::VIEW_JABATAN, self::VIEW_UNIT], true)) {
            $this->view = $view;
            $this->resetPage();
        }
    }

    // ───────────────────────────── Tambah jabatan ─────────────────────────────

    public function openCreate(): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag();
        $this->reset(['newNama', 'newJenis', 'newRm', 'notice']);
        $this->creating = true;
    }

    public function closeCreate(): void
    {
        $this->creating = false;
        $this->resetErrorBag();
    }

    public function saveCreate(MasterFormasiService $service): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag('create');

        try {
            $position = $service->createPoolPosition(['nama' => $this->newNama, 'jenis' => $this->newJenis, 'rm_level' => $this->newRm], auth()->user());
        } catch (FormasiRuleViolationException $e) {
            $this->addError('create', $e->getMessage());

            return;
        }

        $this->cache = null;
        $this->creating = false;
        $this->notice = "Jabatan penampungan '{$position->nama}' dibuat. Buka pada unit yang menggunakannya agar karyawan dapat ditempatkan.";
    }

    // ───────────────────────────── Buka pada unit ─────────────────────────────

    public function startOpen(int $positionId, ?int $unitId = null): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag('open');

        $position = $this->poolPosition($positionId);
        if ($position === null || ! $position->is_active) {
            return;
        }

        // A preselected unit is only kept when it can actually take the jabatan (active and not yet opened).
        $unit = $unitId ? Unit::active()->whereKey($unitId)->first() : null;
        $alreadyOpen = $unit && DB::table('position_unit')->where('position_id', $position->id)->where('unit_id', $unit->id)->exists();

        $this->notice = null;
        $this->openingPositionId = $position->id;
        $this->openUnitId = $unit && ! $alreadyOpen ? $unit->id : null;
    }

    public function cancelOpen(): void
    {
        $this->openingPositionId = null;
        $this->openUnitId = null;
        $this->resetErrorBag('open');
    }

    public function saveOpen(MasterFormasiService $service): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag('open');

        $position = $this->openingPositionId ? Position::find($this->openingPositionId) : null;
        $unit = $this->openUnitId ? Unit::find($this->openUnitId) : null;
        if ($position === null) {
            return;
        }
        if ($unit === null) {
            $this->addError('open', 'Pilih unit kerja tempat jabatan ini dibuka.');

            return;
        }

        try {
            $service->openPoolPosition($unit, $position, auth()->user());
        } catch (FormasiRuleViolationException $e) {
            $this->addError('open', $e->getMessage());

            return;
        }

        $this->cache = null;
        $this->notice = "Jabatan penampungan '{$position->nama}' dibuka pada unit '{$unit->nama}'.";
        $this->cancelOpen();
    }

    // ───────────────────────────── Tutup pada unit ─────────────────────────────

    public function startClose(int $positionId, int $unitId): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag('close');

        $position = $this->poolPosition($positionId);
        $opened = $position && DB::table('position_unit')->where('position_id', $positionId)->where('unit_id', $unitId)->exists();
        if (! $opened) {
            return;
        }

        $this->notice = null;
        $this->closePositionId = $positionId;
        $this->closeUnitId = $unitId;
        $this->closeReason = '';
    }

    public function cancelClose(): void
    {
        $this->closePositionId = null;
        $this->closeUnitId = null;
        $this->closeReason = '';
        $this->resetErrorBag('close');
    }

    public function saveClose(MasterFormasiService $service): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag('close');

        $position = $this->closePositionId ? Position::find($this->closePositionId) : null;
        $unit = $this->closeUnitId ? Unit::find($this->closeUnitId) : null;
        if ($position === null || $unit === null) {
            return;
        }

        try {
            $service->closePoolPosition($unit, $position, $this->closeReason, auth()->user());
        } catch (FormasiRuleViolationException $e) {
            $this->addError('close', $e->getMessage());

            return;
        }

        $this->cache = null;
        $this->notice = "Jabatan penampungan '{$position->nama}' ditutup pada unit '{$unit->nama}'.";
        $this->cancelClose();
    }

    // ───────────────────────────── Ubah nama dan hapus ─────────────────────────────

    public function startRename(int $positionId): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag('rename');

        $position = $this->poolPosition($positionId);
        if ($position === null) {
            return;
        }

        $this->notice = null;
        $this->renamePositionId = $position->id;
        $this->renameNama = $position->nama;
        $this->renameReason = '';
    }

    public function cancelRename(): void
    {
        $this->renamePositionId = null;
        $this->renameNama = '';
        $this->renameReason = '';
        $this->resetErrorBag('rename');
    }

    public function saveRename(MasterFormasiService $service): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag('rename');

        $position = $this->renamePositionId ? Position::find($this->renamePositionId) : null;
        if ($position === null) {
            return;
        }

        try {
            $renamed = $service->renamePoolPosition($position, $this->renameNama, $this->renameReason, auth()->user());
        } catch (FormasiRuleViolationException $e) {
            $this->addError('rename', $e->getMessage());

            return;
        }

        $this->cache = null;
        $this->notice = "Nama jabatan penampungan diubah menjadi '{$renamed->nama}'.";
        $this->cancelRename();
    }

    public function startDelete(int $positionId): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag('delete');

        $position = $this->poolPosition($positionId);
        if ($position === null) {
            return;
        }

        $this->notice = null;
        $this->deletePositionId = $position->id;
        $this->deleteReason = '';
    }

    public function cancelDelete(): void
    {
        $this->deletePositionId = null;
        $this->deleteReason = '';
        $this->resetErrorBag('delete');
    }

    public function saveDelete(MasterFormasiService $service): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag('delete');

        $position = $this->deletePositionId ? Position::find($this->deletePositionId) : null;
        if ($position === null) {
            return;
        }

        try {
            $service->deletePoolPosition($position, $this->deleteReason, auth()->user());
        } catch (FormasiRuleViolationException $e) {
            $this->addError('delete', $e->getMessage());

            return;
        }

        $this->cache = null;
        $this->notice = "Jabatan penampungan '{$position->nama}' dihapus.";
        $this->cancelDelete();
    }

    // ───────────────────────────── Tampilan ─────────────────────────────

    /** @return array{jabatan: int, units: int, holders: int} */
    #[Computed]
    public function stats(): array
    {
        [$jabatan, $unitRows] = $this->data();

        return [
            'jabatan' => $jabatan->count(),
            'units' => $unitRows->where('opened', true)->pluck('unit_nama')->unique()->count(),
            'holders' => (int) $jabatan->sum('holders'),
        ];
    }

    public function render(): View
    {
        $this->authorizeAdmin();
        $this->normalise();

        [$jabatan, $unitRows] = $this->data();
        $term = mb_strtolower($this->search);
        $matches = fn (object $row, array $fields) => $term === '' || collect($fields)->contains(fn (string $f) => str_contains(mb_strtolower((string) $row->{$f}), $term));

        $rows = $this->view === self::VIEW_JABATAN
            ? $jabatan->filter(fn (object $r) => $matches($r, ['nama', 'jenis_label', 'rm_level']))->values()
            : $unitRows->filter(fn (object $r) => $matches($r, ['unit_nama', 'nama', 'jenis_label']))->values();

        $page = max(1, min($this->getPage(), (int) ceil($rows->count() / $this->perPage)));
        if ($page !== $this->getPage()) {
            $this->setPage($page);
        }
        $paginator = new LengthAwarePaginator($rows->forPage($page, $this->perPage)->values(), $rows->count(), $this->perPage, $page);

        return view('livewire.admin.master.pool-positions', [
            'rows' => $paginator,
            'stats' => $this->stats,
            'perPageOptions' => $this->perPageOptions(),
            'jenisLabels' => self::JENIS_LABELS,
            'rmLevels' => MasterFormasiService::POOL_RM_LEVELS,
            'openingPosition' => $this->openingPositionId ? Position::find($this->openingPositionId) : null,
            'openableUnits' => $this->openingPositionId ? $this->openableUnits($this->openingPositionId) : collect(),
            'closingPosition' => $this->closePositionId ? Position::find($this->closePositionId) : null,
            'closingUnit' => $this->closeUnitId ? Unit::find($this->closeUnitId) : null,
            'renamingPosition' => $this->renamePositionId ? Position::find($this->renamePositionId) : null,
            'deletingPosition' => $this->deletePositionId ? Position::find($this->deletePositionId) : null,
        ]);
    }

    /**
     * Seluruh jabatan penampungan beserta pembukaannya pada unit dan jumlah karyawan. Karyawan pada jabatan penampungan yang
     * belum dibuka pada unitnya (mis. diimpor lebih dulu) tetap tampil, agar ketidaksesuaian terlihat. Pencocokan teks
     * karyawan dengan nama master tidak peka huruf besar-kecil dan spasi tepi, sama dengan perilaku MySQL pada kueri.
     *
     * @return array{0: Collection<int, object>, 1: Collection<int, object>}
     */
    private function data(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $norm = fn (?string $text): string => mb_strtolower(trim((string) $text));

        $positions = Position::where(fn ($q) => $q->where('is_overflow', true)->orWhere('is_diperban', true))
            ->orderBy('rm_level')->orderBy('nama')->get();

        $links = DB::table('position_unit')
            ->join('units', 'units.id', '=', 'position_unit.unit_id')
            ->whereIn('position_unit.position_id', $positions->pluck('id'))
            ->get(['position_unit.position_id', 'units.id as unit_id', 'units.nama as unit_nama']);

        // Pemegang per (unit, jabatan) dengan kunci yang dinormalisasi.
        $holders = [];
        foreach (Employee::query()
            ->aktif()
            ->whereIn('jabatan', $positions->pluck('nama'))
            ->selectRaw('unit_kerja, jabatan, count(*) as total')
            ->groupBy('unit_kerja', 'jabatan')
            ->get() as $row) {
            $key = $norm($row->unit_kerja).'|'.$norm($row->jabatan);
            $holders[$key] ??= ['unit_kerja' => $row->unit_kerja, 'unit' => $norm($row->unit_kerja), 'jabatan' => $norm($row->jabatan), 'total' => 0];
            $holders[$key]['total'] += (int) $row->total;
        }
        $holdersByJabatan = collect($holders)->groupBy('jabatan')->map(fn (Collection $g) => (int) $g->sum('total'));
        $unitsByName = Unit::get(['id', 'nama'])->keyBy(fn (Unit $u) => $norm($u->nama));

        $jabatan = $positions->map(function (Position $p) use ($links, $holdersByJabatan, $norm): object {
            $jenis = $p->is_diperban ? 'diperbantukan' : 'advisor';

            return (object) [
                'id' => $p->id,
                'nama' => $p->nama,
                'jenis' => $jenis,
                'jenis_label' => self::JENIS_LABELS[$jenis],
                'rm_level' => $p->rm_level,
                'is_active' => $p->is_active,
                'units' => $links->where('position_id', $p->id)->count(),
                'holders' => (int) $holdersByJabatan->get($norm($p->nama), 0),
            ];
        })->values();

        $byId = $jabatan->keyBy('id');
        $byName = $jabatan->keyBy(fn (object $j) => $norm($j->nama));

        $openedKeys = [];
        $opened = $links->map(function ($link) use ($byId, $holders, $norm, &$openedKeys): object {
            $p = $byId[$link->position_id];
            $key = $norm($link->unit_nama).'|'.$norm($p->nama);
            $openedKeys[$key] = true;

            return (object) [
                'unit_id' => $link->unit_id, 'unit_nama' => $link->unit_nama, 'position_id' => $p->id, 'nama' => $p->nama,
                'jenis_label' => $p->jenis_label, 'rm_level' => $p->rm_level, 'opened' => true,
                'holders' => (int) ($holders[$key]['total'] ?? 0),
            ];
        });

        $stray = collect($holders)
            ->reject(fn (array $h, string $key) => isset($openedKeys[$key]))
            ->map(function (array $h) use ($byName, $unitsByName): ?object {
                $p = $byName->get($h['jabatan']);
                if ($p === null) {
                    return null;
                }

                return (object) [
                    'unit_id' => $unitsByName->get($h['unit'])?->id, 'unit_nama' => $h['unit_kerja'], 'position_id' => $p->id, 'nama' => $p->nama,
                    'jenis_label' => $p->jenis_label, 'rm_level' => $p->rm_level, 'opened' => false, 'holders' => $h['total'],
                ];
            })
            ->filter();

        $unitRows = $opened->concat($stray)->sortBy([['unit_nama', 'asc'], ['nama', 'asc']])->values();

        return $this->cache = [$jabatan, $unitRows];
    }

    /** Jabatan penampungan dengan id ini, atau null bila tidak ada atau bukan jabatan penampungan. */
    private function poolPosition(int $positionId): ?Position
    {
        $position = Position::find($positionId);

        return $position && ($position->is_overflow || $position->is_diperban) ? $position : null;
    }

    /** @return Collection<int, Unit> unit aktif yang belum membuka jabatan ini */
    private function openableUnits(int $positionId): Collection
    {
        $already = DB::table('position_unit')->where('position_id', $positionId)->pluck('unit_id');

        return Unit::active()->whereNotIn('id', $already)->orderBy('urutan')->orderBy('nama')->get(['id', 'nama']);
    }

    private function normalise(): void
    {
        $this->search = mb_substr(trim($this->search), 0, self::SEARCH_MAX_LENGTH);

        if (! in_array($this->view, [self::VIEW_JABATAN, self::VIEW_UNIT], true)) {
            $this->view = self::VIEW_JABATAN;
        }

        if (! in_array($this->perPage, $this->perPageOptions(), true)) {
            $this->perPage = $this->defaultPerPage();
        }
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }

    /** @return array<int, int> Opsi baris per halaman; satu standar untuk seluruh tabel (config hris.pagination). */
    protected function perPageOptions(): array
    {
        return array_map('intval', config('hris.pagination.options'));
    }

    protected function defaultPerPage(): int
    {
        return (int) config('hris.pagination.default');
    }
}
