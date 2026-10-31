<?php

namespace App\Services\Formasi;

use App\Exceptions\FormasiRuleViolationException;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
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

    // ─────────────────────────────── Unit kerja ───────────────────────────────

    /**
     * @param  array{nama: string, kode?: ?string, wilayah: string, urutan?: ?int}  $data
     */
    public function createUnit(array $data, User $user): Unit
    {
        return DB::transaction(function () use ($data, $user): Unit {
            $nama = trim((string) ($data['nama'] ?? ''));
            if ($nama === '') {
                throw new FormasiRuleViolationException('Nama unit kerja wajib diisi.');
            }
            $this->assertUnitNameFree($nama);
            $this->assertKnownWilayah($data['wilayah'] ?? null);

            $unit = Unit::create([
                'nama' => $nama,
                'kode' => $data['kode'] ?? null,
                'wilayah' => $data['wilayah'],
                'urutan' => $data['urutan'] ?? ((int) Unit::max('urutan') + 1),
                'is_active' => true,
            ]);

            MasterAuditLog::record(
                $user, 'unit.created', 'unit', $unit->id, $unit->nama, null,
                $unit->only(['nama', 'kode', 'wilayah', 'urutan'])
            );

            return $unit;
        });
    }

    /**
     * Mengubah atribut unit. Perubahan nama (rename) menyelaraskan teks unit pada pegawai
     * dalam transaksi yang sama; riwayat jabatan tetap memakai nama lama.
     *
     * @param  array{nama?: string, kode?: ?string, wilayah?: string, urutan?: ?int}  $data
     */
    public function updateUnit(Unit $unit, array $data, ?string $reason, User $user): Unit
    {
        return DB::transaction(function () use ($unit, $data, $reason, $user): Unit {
            $changes = $this->changedAttributes($unit, Arr::only($data, ['nama', 'kode', 'wilayah', 'urutan']));
            if ($changes === []) {
                return $unit;
            }

            if (isset($changes['wilayah'])) {
                $this->assertKnownWilayah($changes['wilayah']);
            }

            $isRename = isset($changes['nama']);
            if ($isRename) {
                $this->assertNotBlocked($this->previewImpact('unit.rename', ['unit_id' => $unit->id, 'nama' => $changes['nama']]));
                $reason = $this->requireReason($reason);
            }

            $old = Arr::only($unit->getAttributes(), array_keys($changes));
            $oldName = $unit->nama;
            $unit->update($changes);

            if ($isRename) {
                Employee::where('unit_kerja', $oldName)->update(['unit_kerja' => $unit->nama]);
            }

            MasterAuditLog::record(
                $user, $isRename ? 'unit.renamed' : 'unit.updated', 'unit', $unit->id, $unit->nama,
                $old, $changes, $isRename ? $reason : $this->cleanReason($reason)
            );

            return $unit;
        });
    }

    public function setUnitActive(Unit $unit, bool $active, ?string $reason, User $user): void
    {
        DB::transaction(function () use ($unit, $active, $reason, $user): void {
            if ($unit->is_active === $active) {
                return;
            }

            if (! $active) {
                $this->assertNotBlocked($this->previewImpact('unit.deactivate', ['unit_id' => $unit->id]));
                $reason = $this->requireReason($reason);
            }

            $unit->update(['is_active' => $active]);

            MasterAuditLog::record(
                $user, $active ? 'unit.activated' : 'unit.deactivated', 'unit', $unit->id, $unit->nama,
                ['is_active' => ! $active], ['is_active' => $active],
                $active ? $this->cleanReason($reason) : $reason
            );
        });
    }

    public function deleteUnit(Unit $unit, ?string $reason, User $user): void
    {
        DB::transaction(function () use ($unit, $reason, $user): void {
            $this->assertNotBlocked($this->previewImpact('unit.delete', ['unit_id' => $unit->id]));
            $reason = $this->requireReason($reason);

            $snapshot = $unit->only(['nama', 'kode', 'wilayah', 'urutan']);
            $unit->delete();

            MasterAuditLog::record($user, 'unit.deleted', 'unit', $unit->id, $snapshot['nama'], $snapshot, null, $reason);
        });
    }

    // ───────────────────────────── Jabatan (positions) ─────────────────────────────

    /**
     * @param  array{nama: string, bidang?: ?string, level: string, rm_level?: ?string}  $data
     */
    public function createPosition(array $data, User $user): Position
    {
        return DB::transaction(function () use ($data, $user): Position {
            $nama = trim((string) ($data['nama'] ?? ''));
            if ($nama === '') {
                throw new FormasiRuleViolationException('Nama jabatan wajib diisi.');
            }
            $this->assertPositionNameFree($nama);
            $level = $data['level'] ?? null;
            $this->assertKnownLevel($level);
            $bidang = $data['bidang'] ?? null;
            $this->assertKnownBidang($bidang);
            $rm = $level === 'Karpel' ? null : ($data['rm_level'] ?? null);
            $this->assertKnownRm($rm);

            $position = Position::create([
                'nama' => $nama,
                'bidang' => $bidang,
                'level' => $level,
                'rm_level' => $rm,
                'is_active' => true,
            ]);

            MasterAuditLog::record(
                $user, 'position.created', 'position', $position->id, $position->nama, null,
                $position->only(['nama', 'bidang', 'level', 'rm_level'])
            );

            return $position;
        });
    }

    /**
     * Mengubah atribut jabatan. Perubahan nama menyelaraskan teks jabatan pada pegawai
     * dalam transaksi yang sama; perubahan Level RM berlaku untuk seluruh unit pemakai jabatan.
     *
     * @param  array{nama?: string, bidang?: ?string, level?: string, rm_level?: ?string}  $data
     */
    public function updatePosition(Position $position, array $data, ?string $reason, User $user): Position
    {
        return DB::transaction(function () use ($position, $data, $reason, $user): Position {
            $incoming = Arr::only($data, ['nama', 'bidang', 'level', 'rm_level']);
            if (($incoming['level'] ?? $position->level) === 'Karpel') {
                $incoming['rm_level'] = null;
            }

            $changes = $this->changedAttributes($position, $incoming);
            if ($changes === []) {
                return $position;
            }

            if (isset($changes['level'])) {
                $this->assertKnownLevel($changes['level']);
            }
            if (array_key_exists('bidang', $changes)) {
                $this->assertKnownBidang($changes['bidang']);
            }
            if (array_key_exists('rm_level', $changes)) {
                $this->assertKnownRm($changes['rm_level']);
            }

            $isRename = isset($changes['nama']);
            $needsReason = $isRename || array_key_exists('rm_level', $changes);

            if ($isRename) {
                $this->assertNotBlocked($this->previewImpact('position.rename', ['position_id' => $position->id, 'nama' => $changes['nama']]));
            }
            if (array_key_exists('rm_level', $changes)) {
                $this->assertNotBlocked($this->previewImpact('position.rm_change', ['position_id' => $position->id, 'rm_level' => $changes['rm_level']]));
            }
            $reason = $needsReason ? $this->requireReason($reason) : $this->cleanReason($reason);

            $old = Arr::only($position->getAttributes(), array_keys($changes));
            $oldName = $position->nama;
            $position->update($changes);

            if ($isRename) {
                Employee::where('jabatan', $oldName)->update(['jabatan' => $position->nama]);
            }

            MasterAuditLog::record(
                $user, $isRename ? 'position.renamed' : 'position.updated', 'position', $position->id, $position->nama,
                $old, $changes, $reason
            );

            return $position;
        });
    }

    public function setPositionActive(Position $position, bool $active, ?string $reason, User $user): void
    {
        DB::transaction(function () use ($position, $active, $reason, $user): void {
            if ($position->is_active === $active) {
                return;
            }

            if (! $active) {
                $this->assertNotBlocked($this->previewImpact('position.deactivate', ['position_id' => $position->id]));
                $reason = $this->requireReason($reason);
            }

            $position->update(['is_active' => $active]);

            MasterAuditLog::record(
                $user, $active ? 'position.activated' : 'position.deactivated', 'position', $position->id, $position->nama,
                ['is_active' => ! $active], ['is_active' => $active],
                $active ? $this->cleanReason($reason) : $reason
            );
        });
    }

    public function deletePosition(Position $position, ?string $reason, User $user): void
    {
        DB::transaction(function () use ($position, $reason, $user): void {
            $this->assertNotBlocked($this->previewImpact('position.delete', ['position_id' => $position->id]));
            $reason = $this->requireReason($reason);

            $snapshot = $position->only(['nama', 'bidang', 'level', 'rm_level']);
            $position->delete();

            MasterAuditLog::record($user, 'position.deleted', 'position', $position->id, $snapshot['nama'], $snapshot, null, $reason);
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
            'unit.deactivate' => $this->previewUnitDeactivate((int) $params['unit_id']),
            'unit.delete' => $this->previewUnitDelete((int) $params['unit_id']),
            'unit.rename' => $this->previewUnitRename((int) $params['unit_id'], (string) $params['nama']),
            'position.deactivate' => $this->previewPositionDeactivate((int) $params['position_id']),
            'position.delete' => $this->previewPositionDelete((int) $params['position_id']),
            'position.rename' => $this->previewPositionRename((int) $params['position_id'], (string) $params['nama']),
            'position.rm_change' => $this->previewPositionRmChange((int) $params['position_id']),
            default => throw new InvalidArgumentException("Operasi pratinjau tidak dikenal: {$operation}"),
        };
    }

    private function previewUnitDeactivate(int $unitId): ImpactResult
    {
        $unit = Unit::findOrFail($unitId);
        $employees = Employee::where('unit_kerja', $unit->nama)->count();

        if ($employees > 0) {
            return ImpactResult::blocked(
                ["Unit '{$unit->nama}' tidak dapat dinonaktifkan: {$employees} pegawai masih tercatat di unit ini. Pindahkan pegawai tersebut melalui Mutasi terlebih dahulu."],
                $employees
            );
        }

        return ImpactResult::ok();
    }

    private function previewUnitDelete(int $unitId): ImpactResult
    {
        $unit = Unit::findOrFail($unitId);
        $employees = Employee::where('unit_kerja', $unit->nama)->count();
        $seats = DB::table('position_unit')->where('unit_id', $unit->id)->count();
        $history = JobHistory::where('unit_kerja', $unit->nama)->count();
        $blockers = [];

        if ($employees > 0) {
            $blockers[] = "{$employees} pegawai masih tercatat di unit ini.";
        }
        if ($seats > 0) {
            $blockers[] = "Unit ini masih memiliki {$seats} kursi formasi.";
        }
        if ($history > 0) {
            $blockers[] = "Unit ini tercatat pada {$history} riwayat jabatan.";
        }

        if ($blockers === []) {
            return ImpactResult::ok();
        }

        return ImpactResult::blocked(
            ["Unit '{$unit->nama}' tidak dapat dihapus. ".implode(' ', $blockers).' Gunakan fitur Nonaktifkan.'],
            $employees
        );
    }

    private function previewUnitRename(int $unitId, string $nama): ImpactResult
    {
        $unit = Unit::findOrFail($unitId);

        if (Unit::where('nama', $nama)->where('id', '!=', $unit->id)->exists()) {
            return ImpactResult::blocked(["Nama unit '{$nama}' sudah digunakan oleh unit lain."]);
        }

        $employees = Employee::where('unit_kerja', $unit->nama)->count();

        return ImpactResult::ok(
            $employees > 0 ? ["{$employees} pegawai akan ikut diperbarui nama unitnya; riwayat jabatan tetap memakai nama lama."] : [],
            $employees
        );
    }

    private function previewPositionDeactivate(int $positionId): ImpactResult
    {
        $position = Position::findOrFail($positionId);
        $employees = Employee::where('jabatan', $position->nama)->count();

        if ($employees > 0) {
            return ImpactResult::blocked(
                ["Jabatan '{$position->nama}' tidak dapat dinonaktifkan: {$employees} pegawai masih memegang jabatan ini. Pindahkan pegawai tersebut melalui Mutasi terlebih dahulu."],
                $employees
            );
        }

        return ImpactResult::ok();
    }

    private function previewPositionDelete(int $positionId): ImpactResult
    {
        $position = Position::findOrFail($positionId);
        $employees = Employee::where('jabatan', $position->nama)->count();
        $seats = DB::table('position_unit')->where('position_id', $position->id)->count();
        $history = JobHistory::where('jabatan', $position->nama)->count();
        $blockers = [];

        if ($employees > 0) {
            $blockers[] = "{$employees} pegawai masih memegang jabatan ini.";
        }
        if ($seats > 0) {
            $blockers[] = "Jabatan ini masih menjadi {$seats} kursi formasi.";
        }
        if ($history > 0) {
            $blockers[] = "Jabatan ini tercatat pada {$history} riwayat jabatan.";
        }

        if ($blockers === []) {
            return ImpactResult::ok();
        }

        return ImpactResult::blocked(
            ["Jabatan '{$position->nama}' tidak dapat dihapus. ".implode(' ', $blockers).' Gunakan fitur Nonaktifkan.'],
            $employees
        );
    }

    private function previewPositionRename(int $positionId, string $nama): ImpactResult
    {
        $position = Position::findOrFail($positionId);

        if (Position::where('nama', $nama)->where('id', '!=', $position->id)->exists()) {
            return ImpactResult::blocked(["Nama jabatan '{$nama}' sudah digunakan oleh jabatan lain."]);
        }

        $employees = Employee::where('jabatan', $position->nama)->count();

        return ImpactResult::ok(
            $employees > 0 ? ["{$employees} pegawai akan ikut diperbarui nama jabatannya; riwayat jabatan tetap memakai nama lama."] : [],
            $employees
        );
    }

    private function previewPositionRmChange(int $positionId): ImpactResult
    {
        $position = Position::findOrFail($positionId);
        $units = DB::table('position_unit')->where('position_id', $position->id)->count();

        return ImpactResult::ok(
            $units > 0 ? ["Perubahan Level RM berlaku untuk seluruh unit yang memakai jabatan ini ({$units} unit)."] : [],
            Employee::where('jabatan', $position->nama)->count()
        );
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

    private function assertUnitNameFree(string $nama, ?int $exceptId = null): void
    {
        $query = Unit::where('nama', $nama);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw new FormasiRuleViolationException("Nama unit '{$nama}' sudah digunakan oleh unit lain.");
        }
    }

    private function assertKnownWilayah(mixed $wilayah): void
    {
        if (! in_array($wilayah, Unit::WILAYAH_LIST, true)) {
            throw new FormasiRuleViolationException('Wilayah harus salah satu dari: '.implode('; ', Unit::WILAYAH_LIST).'.');
        }
    }

    /**
     * Hanya atribut yang benar-benar berbeda dari nilai saat ini.
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function changedAttributes(Model $model, array $incoming): array
    {
        $changes = [];
        foreach ($incoming as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }
            if ((string) $model->getAttribute($key) !== (string) $value) {
                $changes[$key] = $key === 'urutan' && $value !== null ? (int) $value : $value;
            }
        }

        return $changes;
    }

    private function cleanReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : $reason;
    }

    private function assertPositionNameFree(string $nama): void
    {
        if (Position::where('nama', $nama)->exists()) {
            throw new FormasiRuleViolationException("Nama jabatan '{$nama}' sudah digunakan oleh jabatan lain.");
        }
    }

    private function assertKnownLevel(mixed $level): void
    {
        if (! is_string($level) || ! array_key_exists($level, Position::LEVEL_LIST)) {
            throw new FormasiRuleViolationException('Level jabatan harus Karpim atau Karpel.');
        }
    }

    private function assertKnownBidang(mixed $bidang): void
    {
        if ($bidang !== null && ! array_key_exists($bidang, Position::BIDANG_LIST)) {
            throw new FormasiRuleViolationException('Bidang harus salah satu dari KEU, TAN, TEK, atau UMU.');
        }
    }

    private function assertKnownRm(mixed $rm): void
    {
        if ($rm !== null && ! in_array($rm, ['RM-1', 'RM-2', 'RM-3'], true)) {
            throw new FormasiRuleViolationException('Level RM jabatan Karpim harus RM-1, RM-2, atau RM-3.');
        }
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
