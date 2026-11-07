<?php

namespace App\Livewire\Admin\Master;

use App\Exceptions\FormasiRuleViolationException;
use App\Exports\FormasiExport;
use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Services\Formasi\FormasiDialogs;
use App\Services\Formasi\ImpactResult;
use App\Services\Formasi\MasterFormasiService;
use App\Services\ManPowerPlanningService;
use App\Support\DataTable\Filter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Layar Formasi: mengelola formasi jabatan (jabatan, bidang, kuota) satu unit kerja per kali.
 * Seluruh aturan dan pencatatan riwayat berada di MasterFormasiService; komponen ini
 * hanya menampilkan pratinjau dampak dan meneruskan konfirmasi admin.
 */
class FormasiEditor extends Component
{
    use WithPagination;

    /** Pilihan jumlah baris per halaman pada kedua tabel. */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /** Label status formasi jabatan; dipakai filter, tabel, dan ekspor agar istilahnya sama. */
    public const SEAT_STATE_LABELS = [
        'over' => 'Melebihi kuota',
        'vacant' => 'Ada lowongan',
        'empty' => 'Kosong',
        'full' => 'Penuh',
    ];

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    #[Locked]
    public ?int $unitId = null;

    /** Filter daftar unit (layar awal). */
    #[Url(as: 'q', except: '')]
    public string $unitQuery = '';

    #[Url(as: 'wilayah', except: '')]
    public string $wilayahFilter = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    /** Filter tabel formasi jabatan (layar detail unit). */
    #[Url(as: 'sq', except: '')]
    public string $seatQuery = '';

    #[Url(as: 'rm', except: '')]
    public string $rmFilter = '';

    #[Url(as: 'bidang', except: '')]
    public string $bidangFilter = '';

    /** over | empty | vacant | full | inactive */
    #[Url(as: 'state', except: '')]
    public string $seatStatusFilter = '';

    #[Url(as: 'sort')]
    public string $sortField = 'rm_level';

    #[Url(as: 'direction')]
    public string $sortDirection = 'asc';

    /** Formasi jabatan yang daftar pemegangnya sedang dibuka (drill-down Terisi). */
    #[Locked]
    public ?int $occupantSeatId = null;

    /** Formasi jabatan yang kuota/bidangnya sedang diubah pada dialog. */
    #[Locked]
    public ?int $editSeatId = null;

    /** seat.add | seat.update | seat.remove */
    #[Locked]
    public ?string $pendingOperation = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $pendingParams = [];

    public string $reason = '';

    public ?string $notice = null;

    /** Dialog Tambah Formasi Jabatan (pilih jabatan yang sudah ada) sedang terbuka. */
    public bool $addingSeat = false;

    /** Halaman detail unit membuka komponen ini dengan unit terpilih; daftar unit membukanya tanpa unit. */
    public function mount(?int $unitId = null): void
    {
        $this->authorizeAdmin();
        $this->unitId = $unitId && Unit::whereKey($unitId)->exists() ? $unitId : null;
    }

    /** Kolom yang dapat diurutkan pada tabel formasi jabatan. */
    /** Batas baris daftar pemegang jabatan; bila jumlah terisi lebih besar, drawer menampilkan catatan. */
    public const OCCUPANT_LIMIT = 200;

    private const SORTABLE = ['jabatan', 'rm_level', 'bidang', 'kuota', 'filled'];

    /** Kolom yang dapat diurutkan pada daftar unit. */
    private const OVERVIEW_SORTABLE = ['nama', 'wilayah', 'seats', 'kuota', 'filled'];

    /**
     * Unduh Excel sesuai tampilan: daftar unit (bila belum memilih unit) atau formasi jabatan unit terpilih,
     * lengkap dengan pencarian, filter, dan urutan yang sedang aktif (seluruh halaman, bukan hanya halaman ini).
     */
    public function exportFormasi(): BinaryFileResponse
    {
        $this->authorizeAdmin();

        if ($this->unit !== null) {
            $unit = $this->unit;
            $rows = $this->filteredSeats->values()->map(fn (object $seat, int $i): array => [
                $i + 1,
                $unit->nama,
                $seat->jabatan,
                $seat->rm_level ?? '-',
                $seat->bidang ?? '-',
                $seat->kuota,
                $seat->filled,
                self::SEAT_STATE_LABELS[$seat->state],
                $seat->position_active ? 'Aktif' : 'Nonaktif',
            ]);
            $export = new FormasiExport('Formasi Jabatan', ['NO', 'UNIT KERJA', 'JABATAN', 'RM BAND', 'BIDANG', 'KUOTA', 'TERISI', 'STATUS FORMASI', 'STATUS JABATAN'], $rows, auth()->user());
            $filename = 'Formasi_Jabatan_'.str($unit->nama)->slug('_')->limit(40, '').'_PTPN4_Reg5_'.now()->format('Ymd_His').'.xlsx';
        } else {
            $rows = $this->filteredOverview->values()->map(fn (object $row, int $i): array => [
                $i + 1,
                $row->nama,
                $row->wilayah,
                $row->is_active ? 'Aktif' : 'Nonaktif',
                $row->seats,
                $row->kuota,
                $row->filled,
                $row->over,
            ]);
            $export = new FormasiExport('Formasi Unit Kerja', ['NO', 'UNIT KERJA', 'WILAYAH', 'STATUS UNIT', 'JUMLAH JABATAN', 'TOTAL KUOTA', 'TERISI', 'FORMASI MELEBIHI KUOTA'], $rows, auth()->user());
            $filename = 'Formasi_Unit_PTPN4_Reg5_'.now()->format('Ymd_His').'.xlsx';
        }

        return Excel::download($export, $filename);
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 25;
        }
        $this->resetPage();
    }

    /** Pencarian atau filter berubah: kembali ke halaman pertama agar hasil tidak berada di halaman kosong. */
    public function updated(string $property): void
    {
        if (in_array($property, ['unitQuery', 'wilayahFilter', 'statusFilter', 'seatQuery', 'rmFilter', 'bidangFilter', 'seatStatusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, $this->unitId === null ? self::OVERVIEW_SORTABLE : self::SORTABLE, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function resetSeatFilters(): void
    {
        $this->reset(['seatQuery', 'rmFilter', 'bidangFilter', 'seatStatusFilter']);
        $this->resetPage();
    }

    /**
     * Filter definitions of the seat table toolbar; the options are the same lists the filters were validated against.
     *
     * @return array<int, Filter>
     */
    protected function seatFilters(): array
    {
        return [
            Filter::select('rmFilter', 'RM Band', array_combine(array_keys(Position::RM_LEVEL_LIST), array_keys(Position::RM_LEVEL_LIST)))->icon('fas fa-layer-group'),
            Filter::select('bidangFilter', 'Bidang', Position::BIDANG_LIST)->icon('fas fa-sitemap'),
            Filter::select('seatStatusFilter', 'Status', self::SEAT_STATE_LABELS + ['inactive' => 'Jabatan nonaktif'])->icon('fas fa-toggle-on'),
        ];
    }

    /**
     * @return array<string, string> [filter property => value] for seat filters that are set and allowed.
     */
    protected function seatFilterValues(): array
    {
        return $this->allowedFilterValues($this->seatFilters());
    }

    /**
     * Filter definitions of the unit list toolbar.
     *
     * @return array<int, Filter>
     */
    protected function unitFilters(): array
    {
        return [
            Filter::select('wilayahFilter', 'Wilayah', array_combine(Unit::WILAYAH_LIST, Unit::WILAYAH_LIST))->icon('fas fa-location-dot'),
            Filter::select('statusFilter', 'Status', ['active' => 'Aktif', 'inactive' => 'Nonaktif'])->icon('fas fa-toggle-on'),
        ];
    }

    /**
     * @return array<string, string> [filter property => value] for unit filters that are set and allowed.
     */
    protected function unitFilterValues(): array
    {
        return $this->allowedFilterValues($this->unitFilters());
    }

    /**
     * @param  array<int, Filter>  $filters
     * @return array<string, string>
     */
    private function allowedFilterValues(array $filters): array
    {
        $values = [];

        foreach ($filters as $filter) {
            $value = $this->{$filter->property};

            if ($value !== '' && $filter->allows($value)) {
                $values[$filter->property] = $value;
            }
        }

        return $values;
    }

    public function hasSeatFilters(): bool
    {
        return trim($this->seatQuery) !== '' || $this->rmFilter !== '' || $this->bidangFilter !== '' || $this->seatStatusFilter !== '';
    }

    /** Kartu KPI "Melebihi Kuota" berfungsi sebagai pintasan filter status. */
    public function toggleOverQuotaFilter(): void
    {
        $this->seatStatusFilter = $this->seatStatusFilter === 'over' ? '' : 'over';
        $this->resetPage();
    }

    public function viewOccupants(int $seatId): void
    {
        $this->authorizeAdmin();
        if ($this->ownsSeat($seatId)) {
            $this->occupantSeatId = $seatId;
            $this->auditOccupantAccess();
        }
    }

    /**
     * Catat pembukaan daftar pemegang jabatan pada master_audit_logs (UU PDP) karena daftar memuat nama dan tanggal
     * MBT/pensiun. Yang tercatat hanya unit, jabatan, RM Band, bidang, dan jumlah baris; tidak ada nama atau NIK.
     */
    private function auditOccupantAccess(): void
    {
        $seat = $this->occupantSeat;
        $rows = count($this->occupants);
        if ($seat === null || $rows === 0) {
            return;
        }

        MasterAuditLog::record(
            auth()->user(), 'seat_access.view_occupants', 'seat_access', null,
            'Pemegang jabatan '.$seat->jabatan, null,
            ['unit' => $this->unit?->nama, 'jabatan' => $seat->jabatan, 'rm_level' => $seat->rm_level, 'bidang' => $seat->bidang, 'jumlah_baris' => $rows],
            null
        );
    }

    public function closeOccupants(): void
    {
        $this->occupantSeatId = null;
    }

    public function openEditSeat(int $seatId): void
    {
        $this->authorizeAdmin();
        if ($this->ownsSeat($seatId)) {
            $this->editSeatId = $seatId;
        }
    }

    public function closeEditSeat(): void
    {
        $this->editSeatId = null;
    }

    public function openAddSeat(): void
    {
        $this->authorizeAdmin();
        if ($this->unitId === null) {
            return;
        }
        $this->addingSeat = true;
    }

    public function closeAddSeat(): void
    {
        $this->addingSeat = false;
    }

    /**
     * Satu formulir: nama yang sudah terdaftar memakai jabatan itu, nama baru membuat jabatan baru pada master.
     */
    public function startAddJabatan(string $nama, ?string $rmLevel, string $bidang, mixed $kuota): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag('add');
        $nama = trim($nama);

        if ($this->unitId === null) {
            return;
        }
        if ($nama === '' || $bidang === '') {
            $this->addError('add', 'Nama jabatan dan bidang wajib diisi.');

            return;
        }

        $existing = Position::whereRaw('lower(nama) = ?', [mb_strtolower($nama)])->first();

        if ($existing) {
            if (! $existing->is_active || $existing->level !== 'Karpim' || $existing->is_overflow) {
                $this->addError('add', "Jabatan '{$existing->nama}' tidak dapat dijadikan formasi unit (nonaktif, bukan Karpim, atau jabatan penampungan).");

                return;
            }
            if (DB::table('position_unit')->where('unit_id', $this->unitId)->where('position_id', $existing->id)->exists()) {
                $this->addError('add', "Unit ini sudah memiliki formasi untuk jabatan '{$existing->nama}'. Ubah kuota pada formasi yang ada.");

                return;
            }

            $this->addingSeat = false;
            $this->beginPending('seat.add', ['position_id' => $existing->id, 'kuota' => (int) $kuota, 'bidang' => $bidang]);

            return;
        }

        if (! in_array($rmLevel, Position::KARPIM_RM_LEVELS, true)) {
            $this->addError('add', 'RM Band wajib dipilih untuk jabatan baru.');

            return;
        }

        $this->addingSeat = false;
        $this->beginPending('seat.add', [
            'new_position' => ['nama' => $nama, 'rm_level' => $rmLevel],
            'kuota' => (int) $kuota,
            'bidang' => $bidang,
        ]);
    }

    public function startAddSeat(int $positionId, mixed $kuota, string $bidang): void
    {
        $this->authorizeAdmin();
        $this->addingSeat = false;
        $this->beginPending('seat.add', ['position_id' => $positionId, 'kuota' => (int) $kuota, 'bidang' => $bidang]);
    }

    public function startUpdateSeat(int $seatId, mixed $kuota, string $bidang): void
    {
        $this->authorizeAdmin();
        $this->editSeatId = null;
        if (! $this->ownsSeat($seatId)) {
            return;
        }
        $this->beginPending('seat.update', ['seat_id' => $seatId, 'kuota' => (int) $kuota, 'bidang' => $bidang]);
    }

    public function startRemoveSeat(int $seatId): void
    {
        $this->authorizeAdmin();
        if (! $this->ownsSeat($seatId)) {
            return;
        }
        $this->beginPending('seat.remove', ['seat_id' => $seatId]);
    }

    public function cancel(): void
    {
        $this->pendingOperation = null;
        $this->pendingParams = [];
        $this->reason = '';
        $this->resetErrorBag();
    }

    public function confirm(MasterFormasiService $service): void
    {
        $this->authorizeAdmin();

        if ($this->pendingOperation === null || $this->unitId === null) {
            return;
        }

        $user = auth()->user();
        $params = $this->pendingParams;

        try {
            match ($this->pendingOperation) {
                'seat.add' => DB::transaction(function () use ($service, $params, $user): int {
                    $position = isset($params['new_position'])
                        ? $service->createPosition([
                            'nama' => $params['new_position']['nama'],
                            'rm_level' => $params['new_position']['rm_level'],
                            'bidang' => $params['bidang'],
                            'level' => 'Karpim',
                        ], $user)
                        : Position::findOrFail($params['position_id']);

                    return $service->addSeat(
                        Unit::findOrFail($this->unitId),
                        $position,
                        (int) $params['kuota'],
                        $params['bidang'] !== '' ? $params['bidang'] : null,
                        $user
                    );
                }),
                'seat.update' => $service->updateSeat((int) $params['seat_id'], (int) $params['kuota'], $params['bidang'] !== '' ? $params['bidang'] : null, $user),
                'seat.remove' => $service->removeSeat((int) $params['seat_id'], $this->reason, $user),
            };
        } catch (FormasiRuleViolationException $e) {
            $this->addError('confirm', $e->getMessage());

            return;
        }

        $this->notice = 'Perubahan formasi berhasil disimpan dan tercatat pada riwayat perubahan master.';
        $this->cancel();
    }

    /**
     * Pratinjau dampak untuk aksi yang menunggu konfirmasi.
     */
    #[Computed]
    public function impact(): ?ImpactResult
    {
        if ($this->pendingOperation === null || $this->unitId === null) {
            return null;
        }

        $params = $this->pendingParams;

        if (isset($params['new_position'])) {
            return ImpactResult::ok(["Jabatan baru '{$params['new_position']['nama']}' ({$params['new_position']['rm_level']}) akan dibuat pada master jabatan dan ditambahkan ke formasi unit ini."]);
        }

        try {
            return app(MasterFormasiService::class)->previewImpact($this->pendingOperation, match ($this->pendingOperation) {
                'seat.add' => ['unit_id' => $this->unitId, 'position_id' => $params['position_id']],
                'seat.update' => ['seat_id' => $params['seat_id'], 'kuota' => $params['kuota']],
                'seat.remove' => ['seat_id' => $params['seat_id']],
            });
        } catch (FormasiRuleViolationException $e) {
            return ImpactResult::blocked([$e->getMessage()]);
        }
    }

    public function requiresReason(): bool
    {
        return $this->pendingOperation === 'seat.remove';
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function units(): Collection
    {
        return Unit::orderBy('urutan')->orderBy('nama')->get();
    }

    #[Computed]
    public function unit(): ?Unit
    {
        return $this->unitId ? Unit::find($this->unitId) : null;
    }

    /**
     * @return Collection<int, object>
     */
    #[Computed]
    public function seats(): Collection
    {
        $unit = $this->unit;
        if ($unit === null) {
            return collect();
        }

        $filled = Employee::where('unit_kerja', $unit->nama)
            ->selectRaw('jabatan, count(*) as total')
            ->groupBy('jabatan')
            ->pluck('total', 'jabatan');

        return DB::table('position_unit')
            ->join('positions', 'positions.id', '=', 'position_unit.position_id')
            ->where('position_unit.unit_id', $unit->id)
            ->get([
                'position_unit.id', 'position_unit.kuota', 'position_unit.bidang',
                'positions.id as position_id', 'positions.is_active as position_active',
                'positions.nama as jabatan', 'positions.level', 'positions.rm_level',
            ])
            ->map(function (object $seat) use ($filled): object {
                $seat->kuota = (int) $seat->kuota;
                $seat->filled = (int) ($filled[$seat->jabatan] ?? 0);
                $seat->over_quota = $seat->filled > $seat->kuota;
                $seat->state = match (true) {
                    $seat->over_quota => 'over',
                    $seat->filled === 0 => 'empty',
                    $seat->filled < $seat->kuota => 'vacant',
                    default => 'full',
                };

                return $seat;
            })
            ->sortBy(fn (object $seat) => [
                mb_strtolower((string) ($seat->{$this->sortField} ?? '')),
                mb_strtolower($seat->jabatan),
            ], SORT_REGULAR, $this->sortDirection === 'desc')
            ->values();
    }

    /**
     * Formasi jabatan setelah pencarian dan filter diterapkan. KPI dan dialog tetap memakai seluruh formasi unit.
     *
     * @return Collection<int, object>
     */
    #[Computed]
    public function filteredSeats(): Collection
    {
        $term = mb_strtolower(trim($this->seatQuery));

        return $this->seats
            ->when($term !== '', fn ($rows) => $rows->filter(fn ($seat) => str_contains(mb_strtolower($seat->jabatan), $term)))
            ->when($this->rmFilter !== '', fn ($rows) => $rows->where('rm_level', $this->rmFilter))
            ->when($this->bidangFilter !== '', fn ($rows) => $rows->where('bidang', $this->bidangFilter))
            ->when($this->seatStatusFilter === 'inactive', fn ($rows) => $rows->where('position_active', false))
            ->when(in_array($this->seatStatusFilter, ['over', 'empty', 'vacant', 'full'], true), fn ($rows) => $rows->where('state', $this->seatStatusFilter))
            ->values();
    }

    /**
     * Satu halaman dari formasi jabatan yang sudah difilter.
     */
    #[Computed]
    public function pagedSeats(): LengthAwarePaginator
    {
        return $this->paginate($this->filteredSeats);
    }

    /**
     * Satu halaman dari daftar unit yang sudah difilter.
     */
    #[Computed]
    public function pagedOverview(): LengthAwarePaginator
    {
        return $this->paginate($this->filteredOverview);
    }

    /**
     * Bahan saran ketik: jabatan Karpim aktif (bukan penampungan) milik unit sejenis (kode unit sama) yang belum menjadi formasi unit terpilih.
     *
     * @return Collection<int, Position>
     */
    #[Computed]
    public function addablePositions(): Collection
    {
        $unit = $this->unit;
        if ($unit === null || ! $unit->kode) {
            return collect();
        }

        $seated = DB::table('position_unit')->where('unit_id', $unit->id)->pluck('position_id');
        $sameKind = DB::table('position_unit')
            ->join('units', 'units.id', '=', 'position_unit.unit_id')
            ->where('units.kode', $unit->kode)
            ->pluck('position_unit.position_id');

        return Position::active()
            ->where('level', 'Karpim')
            ->where('is_overflow', false)
            ->whereIn('id', $sameKind)
            ->whereNotIn('id', $seated)
            ->orderBy('nama')
            ->get(['id', 'nama', 'rm_level']);
    }

    /**
     * Lima perubahan terakhir pada unit terpilih (unit itu sendiri dan formasinya).
     *
     * @return Collection<int, MasterAuditLog>
     */
    #[Computed]
    public function recentChanges(): Collection
    {
        $unit = $this->unit;
        if ($unit === null) {
            return collect();
        }

        return MasterAuditLog::where(function ($q) use ($unit): void {
            $q->where(fn ($q) => $q->where('entity_type', 'unit')->where('entity_id', $unit->id))
                ->orWhere(fn ($q) => $q->where('entity_type', 'seat')->where('entity_label', 'like', $unit->nama.' — %'));
        })->latest('id')->limit(5)->get();
    }

    /**
     * Ringkasan formasi jabatan per unit untuk layar awal (sebelum unit dipilih).
     *
     * @return Collection<int, object>
     */
    public function resetUnitFilters(): void
    {
        $this->reset(['unitQuery', 'wilayahFilter', 'statusFilter']);
        $this->resetPage();
    }

    public function hasUnitFilters(): bool
    {
        return trim($this->unitQuery) !== '' || $this->wilayahFilter !== '' || $this->statusFilter !== '';
    }

    /**
     * Daftar unit setelah pencarian, filter, dan urutan diterapkan. KPI tetap memakai seluruh unit.
     *
     * @return Collection<int, object>
     */
    #[Computed]
    public function filteredOverview(): Collection
    {
        $term = mb_strtolower(trim($this->unitQuery));
        $rows = $this->unitOverview
            ->when($term !== '', fn ($rows) => $rows->filter(fn ($row) => str_contains(mb_strtolower($row->nama), $term)))
            ->when($this->wilayahFilter !== '', fn ($rows) => $rows->where('wilayah', $this->wilayahFilter))
            ->when($this->statusFilter === 'active', fn ($rows) => $rows->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($rows) => $rows->where('is_active', false));

        if (in_array($this->sortField, self::OVERVIEW_SORTABLE, true)) {
            $rows = $rows->sortBy(fn (object $row) => is_string($row->{$this->sortField}) ? mb_strtolower($row->{$this->sortField}) : $row->{$this->sortField}, SORT_REGULAR, $this->sortDirection === 'desc');
        }

        return $rows->values();
    }

    #[Computed]
    public function unitOverview(): Collection
    {
        // Pemegang per formasi jabatan: pegawai dicocokkan dengan (nama unit, nama jabatan), sama seperti layar unit.
        $occupied = DB::table('position_unit')
            ->join('units', 'units.id', '=', 'position_unit.unit_id')
            ->join('positions', 'positions.id', '=', 'position_unit.position_id')
            ->leftJoin('employees', fn ($join) => $join
                ->on('employees.unit_kerja', '=', 'units.nama')
                ->on('employees.jabatan', '=', 'positions.nama'))
            ->groupBy('position_unit.id', 'position_unit.unit_id', 'position_unit.kuota')
            ->get(['position_unit.unit_id', 'position_unit.kuota', DB::raw('count(employees.nik) as filled')])
            ->groupBy('unit_id');

        return $this->units->map(function (Unit $unit) use ($occupied): object {
            $rows = $occupied->get($unit->id, collect());

            return (object) [
                'id' => $unit->id,
                'nama' => $unit->nama,
                'wilayah' => $unit->wilayah,
                'is_active' => $unit->is_active,
                'seats' => $rows->count(),
                'kuota' => (int) $rows->sum('kuota'),
                'filled' => (int) $rows->sum('filled'),
                'over' => $rows->filter(fn ($row) => (int) $row->filled > (int) $row->kuota)->count(),
            ];
        });
    }

    /**
     * Pegawai yang tercatat pada nama unit yang tidak ada di Master Unit. Formasi mencocokkan pegawai dengan nama unit,
     * sehingga pegawai ini tidak terhitung pada kolom Terisi; daftar ini membuat selisih tersebut terlihat.
     *
     * @return Collection<int, object{unit_kerja: string, total: int}>
     */
    #[Computed]
    public function unregisteredEmployeeUnits(): Collection
    {
        return Employee::query()
            ->whereNotNull('unit_kerja')
            ->whereNotIn('unit_kerja', Unit::query()->select('nama'))
            ->selectRaw('unit_kerja, count(*) as total')
            ->groupBy('unit_kerja')
            ->orderByDesc('total')
            ->orderBy('unit_kerja')
            ->get();
    }

    /**
     * Total lintas unit untuk KPI layar awal.
     *
     * @return array{units: int, seats: int, kuota: int, filled: int, over: int}
     */
    #[Computed]
    public function overviewStats(): array
    {
        $rows = $this->unitOverview;

        return [
            'units' => $rows->count(),
            'seats' => (int) $rows->sum('seats'),
            'kuota' => (int) $rows->sum('kuota'),
            'filled' => (int) $rows->sum('filled'),
            'over' => (int) $rows->sum('over'),
        ];
    }

    #[Computed]
    public function occupantSeat(): ?object
    {
        return $this->occupantSeatId ? $this->seats->firstWhere('id', $this->occupantSeatId) : null;
    }

    #[Computed]
    public function editSeat(): ?object
    {
        return $this->editSeatId ? $this->seats->firstWhere('id', $this->editSeatId) : null;
    }

    /**
     * Pegawai yang menempati formasi jabatan terbuka, dalam bentuk baris untuk `x-admin.personnel-drawer`.
     * Hanya nama, status, golongan, tanggal MBT/pensiun, dan NIK (khusus untuk tautan profil) yang dimuat; NIK tidak
     * tampil sebagai teks. Tanggal dihitung model Employee (efektif) dan diformat oleh ManPowerPlanningService.
     *
     * @return list<array{nik: string, nama: string, golongan: ?string, status_karyawan: ?string, tanggal_mbt: string, tanggal_pensiun: string}>
     */
    #[Computed]
    public function occupants(): array
    {
        $seat = $this->occupantSeat;
        if ($seat === null || $this->unit === null) {
            return [];
        }

        $mpp = app(ManPowerPlanningService::class);

        return Employee::where('unit_kerja', $this->unit->nama)
            ->where('jabatan', $seat->jabatan)
            ->orderBy('nama')
            ->limit(self::OCCUPANT_LIMIT)
            ->get(['nik', 'nama', 'golongan', 'status_karyawan', 'tanggal_lahir', 'tanggal_pensiun', 'tanggal_mbt'])
            ->map(fn (Employee $employee) => [
                'nik' => $employee->nik,
                'nama' => $employee->nama,
                'golongan' => $employee->golongan ?: null,
                'status_karyawan' => $employee->status_karyawan,
                'tanggal_mbt' => $mpp->dateLabel($employee->effective_tanggal_mbt),
                'tanggal_pensiun' => $mpp->dateLabel($employee->effective_tanggal_pensiun),
            ])
            ->all();
    }

    public function operationTitle(): string
    {
        return match ($this->pendingOperation) {
            'seat.add' => 'Tambah Formasi Jabatan',
            'seat.update' => 'Ubah Formasi Jabatan',
            'seat.remove' => 'Hapus Formasi Jabatan',
            default => '',
        };
    }

    public function render()
    {
        return view('livewire.admin.master.formasi-editor', [
            'bidangList' => Position::BIDANG_LIST,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'seatFilters' => $this->seatFilters(),
            'seatFilterValues' => $this->seatFilterValues(),
            'unitFilters' => $this->unitFilters(),
            'unitFilterValues' => $this->unitFilterValues(),
            'dialogs' => app(FormasiDialogs::class),
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function beginPending(string $operation, array $params): void
    {
        if ($this->unitId === null) {
            return;
        }

        $this->resetErrorBag();
        $this->notice = null;
        $this->reason = '';
        $this->pendingOperation = $operation;
        $this->pendingParams = $params;
    }

    /**
     * Halaman diklem ke halaman terakhir yang ada, mis. setelah baris dihapus atau filter dipersempit.
     */
    private function paginate(Collection $rows): LengthAwarePaginator
    {
        $perPage = in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : 25;
        $page = max(1, min($this->getPage(), (int) ceil($rows->count() / $perPage)));

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);
    }

    private function ownsSeat(int $seatId): bool
    {
        return $this->unitId !== null
            && DB::table('position_unit')->where('id', $seatId)->where('unit_id', $this->unitId)->exists();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }
}
