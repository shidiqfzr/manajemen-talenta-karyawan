<?php

namespace App\Livewire\Admin\Master;

use App\Exceptions\FormasiRuleViolationException;
use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Services\Formasi\ImpactResult;
use App\Services\Formasi\MasterFormasiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Layar Formasi: mengelola kursi (jabatan, bidang, kuota) satu unit kerja per kali.
 * Seluruh aturan dan pencatatan riwayat berada di MasterFormasiService; komponen ini
 * hanya menampilkan pratinjau dampak dan meneruskan konfirmasi admin.
 */
class FormasiEditor extends Component
{
    public ?int $unitId = null;

    public string $search = '';

    /** seat.add | seat.update | seat.remove */
    public ?string $pendingOperation = null;

    /** @var array<string, mixed> */
    public array $pendingParams = [];

    public string $reason = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function selectUnit(int $unitId): void
    {
        $this->authorizeAdmin();
        $this->unitId = Unit::whereKey($unitId)->exists() ? $unitId : null;
        $this->cancel();
        $this->notice = null;
        $this->search = '';
    }

    public function startAddSeat(int $positionId, int $kuota, string $bidang): void
    {
        $this->authorizeAdmin();
        $this->beginPending('seat.add', ['position_id' => $positionId, 'kuota' => $kuota, 'bidang' => $bidang]);
    }

    public function startUpdateSeat(int $seatId, int $kuota, string $bidang): void
    {
        $this->authorizeAdmin();
        $this->beginPending('seat.update', ['seat_id' => $seatId, 'kuota' => $kuota, 'bidang' => $bidang]);
    }

    public function startRemoveSeat(int $seatId): void
    {
        $this->authorizeAdmin();
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
                'seat.add' => $service->addSeat(
                    Unit::findOrFail($this->unitId),
                    Position::findOrFail($params['position_id']),
                    (int) $params['kuota'],
                    $params['bidang'] !== '' ? $params['bidang'] : null,
                    $user
                ),
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
        return Unit::active()->orderBy('urutan')->orderBy('nama')->get();
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
            ->orderBy('positions.rm_level')
            ->orderBy('positions.nama')
            ->get([
                'position_unit.id', 'position_unit.kuota', 'position_unit.bidang',
                'positions.nama as jabatan', 'positions.level', 'positions.rm_level',
            ])
            ->map(function (object $seat) use ($filled): object {
                $seat->kuota = (int) $seat->kuota;
                $seat->filled = (int) ($filled[$seat->jabatan] ?? 0);
                $seat->over_quota = $seat->filled > $seat->kuota;

                return $seat;
            });
    }

    /**
     * Jabatan Karpim aktif yang belum menjadi kursi pada unit terpilih.
     *
     * @return Collection<int, Position>
     */
    #[Computed]
    public function addablePositions(): Collection
    {
        if ($this->unitId === null) {
            return collect();
        }

        $seated = DB::table('position_unit')->where('unit_id', $this->unitId)->pluck('position_id');

        return Position::active()
            ->where('level', 'Karpim')
            ->whereNotIn('id', $seated)
            ->when(trim($this->search) !== '', fn ($q) => $q->where('nama', 'like', '%'.trim($this->search).'%'))
            ->orderBy('nama')
            ->limit(50)
            ->get();
    }

    /**
     * Lima perubahan terakhir pada unit terpilih (unit itu sendiri dan kursinya).
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

    public function operationTitle(): string
    {
        return match ($this->pendingOperation) {
            'seat.add' => 'Tambah Kursi',
            'seat.update' => 'Ubah Kursi',
            'seat.remove' => 'Hapus Kursi',
            default => '',
        };
    }

    public function render()
    {
        return view('livewire.admin.master.formasi-editor', [
            'bidangList' => Position::BIDANG_LIST,
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

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }
}
