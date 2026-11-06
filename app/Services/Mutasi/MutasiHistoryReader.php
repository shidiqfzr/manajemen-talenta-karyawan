<?php

namespace App\Services\Mutasi;

use App\Models\JobHistory;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Read side of the mutation audit trail: paginated history, KPIs, batches and detail rows.
 */
class MutasiHistoryReader
{
    /**
     * Get all job histories belonging to a collective mutasi batch.
     */
    public function getMutasiBatch(string $batchId): Collection
    {
        return JobHistory::with(['employee', 'creator'])
            ->where('batch_id', $batchId)
            ->orderBy('chain_order')
            ->get();
    }

    /**
     * Build the query for mutation history (reusable for pagination and Excel export - DRY).
     */
    public function getMutasiHistoryQuery(
        ?string $search = null,
        ?string $unitKerja = null,
        ?string $jenisMutasi = null,
        ?bool $isApplied = null,
        bool $onlyDue = false,
        string $sortBy = 'tmt_awal',
        string $sortDirection = 'desc',
        ?string $sumber = null,
    ): Builder {
        $allowedSorts = ['tmt_awal', 'id', 'jabatan', 'unit_kerja', 'jenis_mutasi', 'nomor_sk'];
        $sortCol = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'tmt_awal';
        $direction = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        return JobHistory::with(['employee', 'creator'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('jabatan', 'like', "%{$search}%")
                        ->orWhere('unit_kerja', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($q3) => $q3->where('nama', 'like', "%{$search}%")
                            ->orWhere('nik', 'like', "%{$search}%")
                        );
                });
            })
            ->bySumber($sumber)
            ->when($unitKerja, fn ($q) => $q->where('unit_kerja', $unitKerja))
            ->when($jenisMutasi, fn ($q) => $q->where('jenis_mutasi', $jenisMutasi))
            ->when($isApplied !== null, fn ($q) => $q->where('is_applied', $isApplied))
            ->when($onlyDue, fn ($q) => $q->where('is_applied', false)->where('tmt_awal', '<=', Carbon::today()->toDateString()))
            ->orderBy($sortCol, $direction)
            ->orderByDesc('id');
    }

    /**
     * Paginated system-wide mutation history (all job_histories, newest first).
     */
    public function getMutasiHistory(
        ?string $search = null,
        ?string $unitKerja = null,
        ?string $jenisMutasi = null,
        ?bool $isApplied = null,
        bool $onlyDue = false,
        int $perPage = 15,
        string $sortBy = 'tmt_awal',
        string $sortDirection = 'desc',
        ?string $sumber = null,
    ): LengthAwarePaginator {
        return $this->getMutasiHistoryQuery(
            search: $search,
            unitKerja: $unitKerja,
            jenisMutasi: $jenisMutasi,
            isApplied: $isApplied,
            onlyDue: $onlyDue,
            sortBy: $sortBy,
            sortDirection: $sortDirection,
            sumber: $sumber,
        )->paginate($perPage);
    }

    /**
     * Get aggregated KPI metrics for mutation history & audit log.
     *
     * @return array{total: int, active: int, scheduled: int, due: int}
     */
    public function getMutasiHistoryKpis(?string $sumber = null): array
    {
        return [
            'total' => JobHistory::bySumber($sumber)->count(),
            'active' => JobHistory::bySumber($sumber)->where('is_applied', true)->whereNull('tmt_akhir')->count(),
            'scheduled' => JobHistory::bySumber($sumber)->where('is_applied', false)->count(),
            'due' => JobHistory::bySumber($sumber)->where('is_applied', false)
                ->where('tmt_awal', '<=', Carbon::today()->toDateString())
                ->count(),
        ];
    }

    /**
     * Get count of pending scheduled mutations.
     */
    public function getScheduledMutationsCount(): int
    {
        return JobHistory::where('is_applied', false)->count();
    }

    /**
     * One job history row with the relations needed by the audit-trail detail modal.
     */
    public function getMutasiDetail(int $id): JobHistory
    {
        return JobHistory::with(['employee', 'creator'])->findOrFail($id);
    }
}
