<?php

namespace App\Services\Formasi;

use App\Exceptions\FormasiRuleViolationException;
use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya jalur ubah master unit, jabatan, dan kursi formasi (DOMAIN.md §3.5).
 * Setiap perubahan: dipratinjau dampaknya, diterapkan dalam satu transaksi, dan
 * dicatat pada master_audit_logs. Pemindahan pegawai tetap melalui modul Mutasi.
 */
class MasterFormasiService
{
    private const REASON_MIN_LENGTH = 10;

    // ───────────────────────── Kursi (position_unit) ─────────────────────────

    /**
     * @return int id kursi (position_unit) yang dibuat
     */
    public function addSeat(Unit $unit, Position $position, int $kuota, ?string $bidang, User $user): int
    {
        return DB::transaction(function () use ($unit, $position, $kuota, $bidang, $user): int {
            $this->assertSeatInput($position, $kuota, $bidang);
            $this->assertNotBlocked($this->previewImpact('seat.add', ['unit_id' => $unit->id, 'position_id' => $position->id]));

            $id = DB::table('position_unit')->insertGetId([
                'unit_id' => $unit->id,
                'position_id' => $position->id,
                'kuota' => $kuota,
                'bidang' => $bidang,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            MasterAuditLog::record(
                $user, 'seat.added', 'seat', $id, $this->seatLabel($unit->nama, $position->nama),
                null, ['kuota' => $kuota, 'bidang' => $bidang]
            );

            return $id;
        });
    }

    public function updateSeat(int $positionUnitId, int $kuota, ?string $bidang, User $user): void
    {
        DB::transaction(function () use ($positionUnitId, $kuota, $bidang, $user): void {
            $seat = $this->findSeat($positionUnitId);
            $position = Position::findOrFail($seat->position_id);
            $this->assertSeatInput($position, $kuota, $bidang);

            if ($seat->kuota === $kuota && $seat->bidang === $bidang) {
                return;
            }

            $this->assertNotBlocked($this->previewImpact('seat.update', ['seat_id' => $positionUnitId, 'kuota' => $kuota]));

            DB::table('position_unit')->where('id', $positionUnitId)->update([
                'kuota' => $kuota,
                'bidang' => $bidang,
                'updated_at' => now(),
            ]);

            MasterAuditLog::record(
                $user, 'seat.updated', 'seat', $positionUnitId, $this->seatLabel($seat->unit_nama, $seat->jabatan),
                ['kuota' => $seat->kuota, 'bidang' => $seat->bidang, 'filled' => $this->filled($seat->unit_nama, $seat->jabatan)],
                ['kuota' => $kuota, 'bidang' => $bidang]
            );
        });
    }

    public function removeSeat(int $positionUnitId, ?string $reason, User $user): void
    {
        DB::transaction(function () use ($positionUnitId, $reason, $user): void {
            $seat = $this->findSeat($positionUnitId);
            $this->assertNotBlocked($this->previewImpact('seat.remove', ['seat_id' => $positionUnitId]));
            $reason = $this->requireReason($reason);

            DB::table('position_unit')->where('id', $positionUnitId)->delete();

            MasterAuditLog::record(
                $user, 'seat.removed', 'seat', $positionUnitId, $this->seatLabel($seat->unit_nama, $seat->jabatan),
                ['kuota' => $seat->kuota, 'bidang' => $seat->bidang, 'filled' => 0],
                null,
                $reason
            );
        });
    }

    // ───────────────────────────── Pratinjau dampak ─────────────────────────────

    /**
     * Pratinjau dampak sebuah perubahan; dipakai panel dampak di layar dan pemeriksaan sisi server.
     *
     * @param  array<string, mixed>  $params
     */
    public function previewImpact(string $operation, array $params): ImpactResult
    {
        return match ($operation) {
            'seat.add' => $this->previewSeatAdd((int) $params['unit_id'], (int) $params['position_id']),
            'seat.update' => $this->previewSeatUpdate((int) $params['seat_id'], (int) $params['kuota']),
            'seat.remove' => $this->previewSeatRemove((int) $params['seat_id']),
            default => throw new InvalidArgumentException("Operasi pratinjau tidak dikenal: {$operation}"),
        };
    }

    private function previewSeatAdd(int $unitId, int $positionId): ImpactResult
    {
        $unit = Unit::findOrFail($unitId);
        $position = Position::findOrFail($positionId);
        $blockers = [];

        if (! $unit->is_active) {
            $blockers[] = "Unit '{$unit->nama}' nonaktif sehingga tidak dapat menerima kursi baru.";
        }
        if (! $position->is_active) {
            $blockers[] = "Jabatan '{$position->nama}' nonaktif sehingga tidak dapat dijadikan kursi.";
        }
        if (DB::table('position_unit')->where('unit_id', $unitId)->where('position_id', $positionId)->exists()) {
            $blockers[] = "Unit '{$unit->nama}' sudah memiliki kursi untuk jabatan '{$position->nama}'. Ubah kuota pada kursi yang ada.";
        }

        return $blockers === [] ? ImpactResult::ok() : ImpactResult::blocked($blockers);
    }

    private function previewSeatUpdate(int $seatId, int $kuota): ImpactResult
    {
        $seat = $this->findSeat($seatId);
        $filled = $this->filled($seat->unit_nama, $seat->jabatan);

        if ($kuota < $filled) {
            return ImpactResult::ok(
                ["Kuota {$kuota} lebih kecil dari {$filled} pegawai yang menempati kursi ini; kursi akan ditandai melebihi kuota."],
                $filled
            );
        }

        return ImpactResult::ok([], $filled);
    }

    private function previewSeatRemove(int $seatId): ImpactResult
    {
        $seat = $this->findSeat($seatId);
        $filled = $this->filled($seat->unit_nama, $seat->jabatan);

        if ($filled > 0) {
            return ImpactResult::blocked(
                ["Kursi tidak dapat dihapus: {$filled} pegawai masih menempati jabatan '{$seat->jabatan}' di '{$seat->unit_nama}'. Pindahkan pegawai tersebut melalui Mutasi terlebih dahulu."],
                $filled
            );
        }

        return ImpactResult::ok();
    }

    // ─────────────────────────────── Pembantu ───────────────────────────────

    /**
     * Alasan perubahan wajib (minimal 10 karakter setelah dipangkas).
     */
    public function requireReason(?string $reason): string
    {
        $reason = trim((string) $reason);

        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new FormasiRuleViolationException('Alasan perubahan wajib diisi (minimal 10 karakter).');
        }

        return $reason;
    }

    private function assertNotBlocked(ImpactResult $impact): void
    {
        if ($impact->blocked) {
            throw new FormasiRuleViolationException($impact->blockers[0]);
        }
    }

    private function assertSeatInput(Position $position, int $kuota, ?string $bidang): void
    {
        if ($kuota < 1) {
            throw new FormasiRuleViolationException('Kuota harus berupa bilangan bulat minimal 1.');
        }

        if ($bidang !== null && ! array_key_exists($bidang, Position::BIDANG_LIST)) {
            throw new FormasiRuleViolationException('Bidang harus salah satu dari KEU, TAN, TEK, atau UMU.');
        }

        if ($bidang === null && $position->level === 'Karpim') {
            throw new FormasiRuleViolationException("Bidang wajib diisi untuk kursi Karpim jabatan '{$position->nama}'.");
        }
    }

    private function findSeat(int $positionUnitId): object
    {
        $seat = DB::table('position_unit')
            ->join('units', 'units.id', '=', 'position_unit.unit_id')
            ->join('positions', 'positions.id', '=', 'position_unit.position_id')
            ->where('position_unit.id', $positionUnitId)
            ->first([
                'position_unit.id', 'position_unit.unit_id', 'position_unit.position_id',
                'position_unit.kuota', 'position_unit.bidang',
                'units.nama as unit_nama', 'positions.nama as jabatan',
            ]);

        if ($seat === null) {
            throw new FormasiRuleViolationException('Kursi formasi tidak ditemukan.');
        }

        $seat->kuota = (int) $seat->kuota;

        return $seat;
    }

    private function filled(string $unitNama, string $jabatan): int
    {
        return Employee::where('unit_kerja', $unitNama)->where('jabatan', $jabatan)->count();
    }

    private function seatLabel(string $unitNama, string $jabatan): string
    {
        return "{$unitNama} — {$jabatan}";
    }
}
