<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\MasterAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pemberhentian karyawan dari jabatan (DOMAIN.md §5.1; SECURITY.md §5 Audit Trail).
 *
 * Pensiun normal berjalan otomatis pada tanggal pensiun efektif (`tanggal_pensiun`, atau tanggal lahir + BUP).
 * Perpanjangan masa dinas melewati BUP dilakukan dengan menetapkan `tanggal_pensiun` yang baru pada karyawan;
 * pemberhentian lain (pensiun dini, mengundurkan diri, meninggal dunia, PHK) dilakukan admin dengan tanggal
 * dan SK. Setiap pemberhentian, dalam satu transaksi: riwayat jabatan terbuka ditutup, mutasi terjadwal
 * dibatalkan, karyawan ditandai berhenti, dan peristiwanya dicatat pada Riwayat Perubahan.
 * Data karyawan tidak dihapus; jabatan dan unit terakhir menjadi arsip (UU PDP: retensi sesuai kebijakan).
 */
class PensiunService
{
    /** Alasan pembatalan mutasi terjadwal milik karyawan yang diberhentikan. */
    public const ALASAN_PEMBATALAN = 'Karyawan diberhentikan dari jabatan';

    /**
     * Karyawan aktif yang tanggal pensiunnya sudah tiba pada tanggal acuan.
     *
     * @return Collection<int, Employee>
     */
    public function dueEmployees(?Carbon $asOf = null): Collection
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();

        return Employee::aktif()
            ->orderBy('nik')
            ->get()
            ->filter(fn (Employee $emp): bool => $emp->effective_tanggal_pensiun !== null
                && $emp->effective_tanggal_pensiun->copy()->startOfDay()->lte($asOf))
            ->values();
    }

    /**
     * Memberhentikan semua karyawan yang jatuh tempo pensiun (pekerjaan terjadwal; tanpa pelaku, tercatat sebagai Sistem).
     * Idempotent. `$dryRun` hanya melaporkan siapa yang akan diproses tanpa mengubah data.
     *
     * @return array{count: int, dry_run: bool, items: list<array{nik: string, nama: string, jabatan: ?string, unit_kerja: ?string, tanggal_berhenti: string}>}
     */
    public function processDue(?Carbon $asOf = null, bool $dryRun = false): array
    {
        $items = [];

        foreach ($this->dueEmployees($asOf) as $employee) {
            $items[] = [
                'nik' => (string) $employee->nik,
                'nama' => (string) $employee->nama,
                'jabatan' => $employee->jabatan,
                'unit_kerja' => $employee->unit_kerja,
                'tanggal_berhenti' => $employee->effective_tanggal_pensiun->toDateString(),
            ];

            if (! $dryRun) {
                $this->separate($employee);
            }
        }

        return ['count' => count($items), 'dry_run' => $dryRun, 'items' => $items];
    }

    /**
     * Memberhentikan satu karyawan (atomik). Tanpa `$tanggal`, dipakai tanggal pensiun efektif (hanya untuk alasan
     * Pensiun); alasan lain wajib bertanggal. Mengembalikan false bila sudah berhenti atau tanggalnya tidak ada.
     *
     * @throws InvalidArgumentException alasan tidak baku atau pemberhentian non-pensiun tanpa tanggal
     */
    public function separate(
        Employee $employee,
        string $alasan = Employee::ALASAN_PENSIUN,
        ?Carbon $tanggal = null,
        ?string $nomorSk = null,
        ?Carbon $tanggalSk = null,
        ?User $actor = null,
    ): bool {
        if (! in_array($alasan, Employee::ALASAN_BERHENTI, true)) {
            throw new InvalidArgumentException("Alasan pemberhentian '{$alasan}' tidak dikenal.");
        }

        if ($tanggal === null && $alasan !== Employee::ALASAN_PENSIUN) {
            throw new InvalidArgumentException('Tanggal pemberhentian wajib diisi untuk alasan selain Pensiun.');
        }

        return DB::transaction(function () use ($employee, $alasan, $tanggal, $nomorSk, $tanggalSk, $actor): bool {
            $locked = Employee::where('nik', $employee->nik)->lockForUpdate()->first();
            $tanggal ??= $locked?->effective_tanggal_pensiun;

            if ($locked === null || $locked->isSeparated() || $tanggal === null) {
                return false;
            }

            $tanggal = $tanggal->copy()->startOfDay();
            $catatan = "Berakhir karena {$alasan}".($nomorSk ? " (SK {$nomorSk})" : '');

            // Riwayat terbuka (definitif maupun Pj/Plt) ditutup pada tanggal berhenti, tidak lebih awal dari TMT-nya.
            $closed = JobHistory::query()->active()->where('employee_nik', $locked->nik)->get();
            foreach ($closed as $history) {
                $end = $tanggal->lt($history->tmt_awal) ? $history->tmt_awal->copy() : $tanggal->copy();
                $history->update([
                    'tmt_akhir' => $end->toDateString(),
                    'catatan' => trim(($history->catatan ? $history->catatan.' | ' : '').$catatan),
                ]);
            }

            // Mutasi terjadwal yang belum berlaku tidak boleh menempatkan karyawan yang sudah berhenti.
            $cancelled = JobHistory::query()->scheduled()->where('employee_nik', $locked->nik)->get();
            foreach ($cancelled as $history) {
                $history->update([
                    'cancelled_by' => $actor?->id,
                    'alasan_pembatalan' => self::ALASAN_PEMBATALAN." ({$alasan})",
                ]);
                $history->delete();
            }

            $locked->update([
                'tanggal_berhenti' => $tanggal->toDateString(),
                'alasan_berhenti' => $alasan,
                'nomor_sk_berhenti' => $nomorSk,
                'tanggal_sk_berhenti' => $tanggalSk?->toDateString(),
            ]);

            MasterAuditLog::record(
                $actor,
                'employee.separated',
                'employee',
                null,
                "{$locked->nik} - {$locked->nama}",
                ['jabatan' => $locked->jabatan, 'unit' => $locked->unit_kerja],
                array_filter([
                    'alasan' => $alasan,
                    'tanggal_berhenti' => $tanggal->toDateString(),
                    'nomor_sk' => $nomorSk,
                    'tanggal_sk' => $tanggalSk?->toDateString(),
                    'jumlah' => $closed->count() + $cancelled->count(),
                ], fn ($value) => $value !== null && $value !== ''),
                $actor === null ? 'Diproses otomatis oleh sistem pada tanggal berhenti' : null,
            );

            return true;
        });
    }

    /**
     * Mencatat SK pemberhentian yang terbit setelah pemberhentian otomatis (SK boleh menyusul, tetapi tidak boleh hilang).
     */
    public function catatSk(Employee $employee, string $nomorSk, Carbon $tanggalSk, ?User $actor = null): void
    {
        if (! $employee->isSeparated()) {
            throw new InvalidArgumentException('SK pemberhentian hanya dapat dicatat untuk karyawan yang sudah berhenti.');
        }

        DB::transaction(function () use ($employee, $nomorSk, $tanggalSk, $actor): void {
            $old = array_filter(['nomor_sk' => $employee->nomor_sk_berhenti, 'tanggal_sk' => $employee->tanggal_sk_berhenti?->toDateString()]);

            $employee->update(['nomor_sk_berhenti' => $nomorSk, 'tanggal_sk_berhenti' => $tanggalSk->toDateString()]);

            MasterAuditLog::record(
                $actor,
                'employee.separation_sk_recorded',
                'employee',
                null,
                "{$employee->nik} - {$employee->nama}",
                $old ?: null,
                ['nomor_sk' => $nomorSk, 'tanggal_sk' => $tanggalSk->toDateString()],
            );
        });
    }
}
