<?php

namespace App\Services\Mpp;

use App\Exceptions\MppPeriodRuleViolationException;
use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\MppPeriod;
use App\Models\MppPeriodMember;
use App\Models\User;
use App\Services\ManPowerPlanningService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalur tulis periode bulanan MPP: rekam draf, tutup, dan buka kembali. Setiap perubahan
 * diterapkan dalam satu transaksi bersama baris riwayatnya pada master_audit_logs.
 */
class MppPeriodService
{
    private const REASON_MIN_LENGTH = 10;

    private const ENTITY_TYPE = 'mpp_period';

    /** Perekaman bersamaan untuk bulan yang sama dapat deadlock; Laravel mengulang transaksinya. */
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(private readonly ManPowerPlanningService $planning) {}

    public function find(int $year, int $month): ?MppPeriod
    {
        return MppPeriod::where('year', $year)->where('month', $month)->first();
    }

    /**
     * Rekam atau perbarui draf periode: salinan matriks dan personil di balik tiap sel. $user null berarti
     * penjadwal; riwayat tetap dicatat atas nama "Sistem".
     *
     * @throws MppPeriodRuleViolationException
     */
    public function capture(int $year, int $month, ?User $user): MppPeriod
    {
        $this->assertClosable($year, $month);

        // Instans baru: ManPowerPlanningService menyimpan pembacaan pegawai selama hidupnya, sedangkan
        // perekaman harus selalu membaca data terkini (juga bila layanan ini dipakai berulang kali).
        $planning = app(ManPowerPlanningService::class);
        $matrix = $planning->getMppMatrix($year, $month);
        $members = $planning->getPositionMembers($year, $month);

        return DB::transaction(function () use ($year, $month, $user, $matrix, $members): MppPeriod {
            $period = $this->locked($year, $month);

            if ($period?->status === MppPeriod::STATUS_CLOSED) {
                throw $this->alreadyClosed($year, $month);
            }

            $old = $period ? $this->state($period) : null;

            $period = $period ?? new MppPeriod(['year' => $year, 'month' => $month, 'revision' => 1]);
            $period->fill([
                'status' => MppPeriod::STATUS_DRAFT,
                'snapshot' => $matrix,
                'captured_at' => now(),
                'captured_by' => $user?->id,
            ])->save();

            $period->members()->delete();
            foreach (array_chunk($members, 500) as $chunk) {
                $period->members()->insert(array_map(
                    fn (array $member): array => $member + ['mpp_period_id' => $period->id],
                    $chunk
                ));
            }

            $this->audit($user, 'mpp_period.captured', $period, $old, null);

            return $period;
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Tutup draf yang sudah direkam; yang ditutup adalah isi draf itu (tanpa perekaman ulang diam-diam).
     *
     * @throws MppPeriodRuleViolationException
     */
    public function close(int $year, int $month, ?string $note, User $user): MppPeriod
    {
        $this->assertClosable($year, $month);

        $note = trim((string) $note);

        return DB::transaction(function () use ($year, $month, $note, $user): MppPeriod {
            $period = $this->locked($year, $month);

            if ($period === null) {
                throw new MppPeriodRuleViolationException('Periode '.$this->label($year, $month).' belum memiliki draf.');
            }
            if ($period->status === MppPeriod::STATUS_CLOSED) {
                throw $this->alreadyClosed($year, $month);
            }

            $old = $this->state($period);
            $period->update([
                'status' => MppPeriod::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $user->id,
                'close_note' => $note !== '' ? $note : null,
            ]);

            $this->audit($user, 'mpp_period.closed', $period, $old, $period->close_note);

            return $period;
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Buka kembali periode tertutup menjadi draf dengan nomor revisi bertambah.
     *
     * @throws MppPeriodRuleViolationException
     */
    public function reopen(int $year, int $month, ?string $reason, User $user): MppPeriod
    {
        $this->assertWithinPlanningYears($year);

        $reason = trim((string) $reason);
        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new MppPeriodRuleViolationException('Alasan membuka kembali wajib diisi minimal '.self::REASON_MIN_LENGTH.' karakter.');
        }

        return DB::transaction(function () use ($year, $month, $reason, $user): MppPeriod {
            $period = $this->locked($year, $month);

            if ($period === null || $period->status !== MppPeriod::STATUS_CLOSED) {
                throw new MppPeriodRuleViolationException('Periode '.$this->label($year, $month).' belum ditutup.');
            }

            $old = $this->state($period);
            $period->update([
                'status' => MppPeriod::STATUS_DRAFT,
                'revision' => $period->revision + 1,
                'closed_at' => null,
                'closed_by' => null,
                'close_note' => null,
            ]);

            $this->audit($user, 'mpp_period.reopened', $period, $old, $reason);

            return $period;
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Personil satu sel pada periode, berbentuk sama dengan ManPowerPlanningService::getDrilldownEmployees().
     * Jabatan, unit, dan tanggal berasal dari snapshot; nama, golongan, pensiun, dan status dari data pegawai
     * saat ini ("tidak lagi terdaftar" bila NIK sudah tidak ada).
     *
     * @return list<array<string, mixed>>
     */
    public function membersFor(MppPeriod $period, string $scope, string $rm, string $bidang): array
    {
        $members = $period->members()
            ->where('scope', $scope)->where('rm_level', $rm)->where('bidang', $bidang)
            ->orderBy('id')->get();

        $employees = Employee::whereIn('nik', $members->pluck('employee_nik')->unique()->all())
            ->get()->keyBy('nik');

        return $members->map(function (MppPeriodMember $member) use ($employees): array {
            $emp = $employees->get($member->employee_nik);

            return [
                'id' => $emp?->id,
                'nik' => $member->employee_nik,
                'nama' => $emp?->nama ?? 'tidak lagi terdaftar',
                'jabatan' => $member->jabatan,
                'unit_kerja' => $member->unit_kerja,
                'golongan' => $emp?->golongan ?? '—',
                'tanggal_penempatan' => $this->planning->dateLabel($member->tanggal_penempatan),
                'tanggal_mbt' => $this->planning->dateLabel($member->tanggal_mbt),
                'tanggal_pensiun' => $emp ? $this->planning->dateLabel($emp->effective_tanggal_pensiun) : '—',
                'status_karyawan' => $emp ? ($emp->status_karyawan ?? 'Aktif') : '—',
            ];
        })->all();
    }

    private function assertClosable(int $year, int $month): void
    {
        $this->assertWithinPlanningYears($year);

        if ($month < 1 || $month > 12 || Carbon::create($year, $month, 1)->startOfMonth()->gte(now()->startOfMonth())) {
            throw new MppPeriodRuleViolationException('Periode berjalan atau mendatang belum dapat direkam atau ditutup.');
        }
    }

    private function assertWithinPlanningYears(int $year): void
    {
        if ($year < ManPowerPlanningService::PLANNING_YEAR_MIN || $year > ManPowerPlanningService::PLANNING_YEAR_MAX) {
            throw new MppPeriodRuleViolationException('Tahun perencanaan harus antara '.ManPowerPlanningService::PLANNING_YEAR_MIN.' dan '.ManPowerPlanningService::PLANNING_YEAR_MAX.'.');
        }
    }

    private function locked(int $year, int $month): ?MppPeriod
    {
        return MppPeriod::where('year', $year)->where('month', $month)->lockForUpdate()->first();
    }

    private function alreadyClosed(int $year, int $month): MppPeriodRuleViolationException
    {
        return new MppPeriodRuleViolationException('Periode '.$this->label($year, $month).' sudah ditutup. Buka kembali terlebih dulu.');
    }

    private function label(int $year, int $month): string
    {
        return $this->planning->monthLabel($month, $year);
    }

    /** @return array{status: string, revision: int} */
    private function state(MppPeriod $period): array
    {
        return ['status' => $period->status, 'revision' => $period->revision];
    }

    private function audit(?User $user, string $action, MppPeriod $period, ?array $old, ?string $reason): void
    {
        MasterAuditLog::record(
            $user, $action, self::ENTITY_TYPE, $period->id, 'Periode MPP '.$this->label($period->year, $period->month),
            $old, $this->state($period), $reason
        );
    }
}
