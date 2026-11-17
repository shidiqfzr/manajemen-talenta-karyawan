<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeImportLog;
use App\Models\MutasiChainProposal;
use App\Models\StructureDecree;
use App\Models\User;
use App\Support\HrisPermission;
use Illuminate\Support\Facades\Gate;

/**
 * Pusat Pemberitahuan: daftar pekerjaan SDM yang menunggu tindak lanjut, dihitung langsung dari data
 * (bukan peristiwa tersimpan), sehingga angkanya selalu sama dengan layar tujuannya dan tidak perlu status "dibaca".
 * Setiap butir hanya muncul bila jumlahnya > 0 dan pengguna berwenang menindaklanjutinya (SECURITY.md §3).
 */
class NotificationCenterService
{
    /**
     * @return list<array{key: string, title: string, detail: string, count: int, icon: string, url: string}>
     */
    public function itemsFor(User $user): array
    {
        $items = [];

        $pendingImports = EmployeeImportLog::query()->where('status', EmployeeImportLog::STATUS_PENDING)->count();
        if ($pendingImports > 0) {
            $items[] = [
                'key' => 'import',
                'title' => 'Impor Data Karyawan Menunggu Persetujuan',
                'detail' => $pendingImports.' berkas impor belum diputuskan.',
                'count' => $pendingImports,
                'icon' => 'fas fa-file-import',
                'url' => route('admin.employees.import.batches.index', ['status' => 'pending']),
            ];
        }

        if (Gate::forUser($user)->allows(HrisPermission::MUTASI_APPROVE)) {
            $pendingProposals = MutasiChainProposal::query()->where('status', MutasiChainProposal::STATUS_PENDING)->count();
            if ($pendingProposals > 0) {
                $items[] = [
                    'key' => 'usulan',
                    'title' => 'Usulan Mutasi Berantai Menunggu Persetujuan',
                    'detail' => $pendingProposals.' usulan menunggu keputusan Anda.',
                    'count' => $pendingProposals,
                    'icon' => 'fas fa-diagram-project',
                    'url' => route('admin.mutasi.index', ['tab' => 'usulan']),
                ];
            }
        }

        if (Gate::forUser($user)->allows(HrisPermission::STRUKTUR_APPROVE)) {
            $submitted = StructureDecree::query()->where('status', StructureDecree::STATUS_SUBMITTED)->orderBy('id')->get();
            if ($submitted->isNotEmpty()) {
                $items[] = [
                    'key' => 'sk-struktur',
                    'title' => 'SK Struktur Organisasi Menunggu Persetujuan',
                    'detail' => $submitted->count().' SK struktur organisasi menunggu keputusan Anda.',
                    'count' => $submitted->count(),
                    'icon' => 'fas fa-file-signature',
                    'url' => $submitted->count() === 1 ? route('admin.master.sk.show', $submitted->first()) : route('admin.master.sk.index'),
                ];
            }
        }

        if (Gate::forUser($user)->allows(HrisPermission::STRUKTUR_PREPARE)) {
            $attention = StructureDecree::query()->whereIn('status', [StructureDecree::STATUS_STALE, StructureDecree::STATUS_FAILED])->orderBy('id')->get();
            if ($attention->isNotEmpty()) {
                $items[] = [
                    'key' => 'sk-struktur-tindakan',
                    'title' => 'SK Struktur Organisasi Perlu Tindakan',
                    'detail' => $attention->count().' SK struktur organisasi perlu ditinjau ulang atau diterapkan ulang.',
                    'count' => $attention->count(),
                    'icon' => 'fas fa-triangle-exclamation',
                    'url' => $attention->count() === 1 ? route('admin.master.sk.show', $attention->first()) : route('admin.master.sk.index'),
                ];
            }
        }

        $approachingMbt = $this->approachingMbtCount();
        if ($approachingMbt > 0) {
            $items[] = [
                'key' => 'mbt',
                'title' => 'Karyawan Menjelang Masa Bebas Tugas',
                'detail' => $approachingMbt.' karyawan memasuki MBT dalam '.KepegawaianPolicy::MBT_PERINGATAN_BULAN.' bulan ke depan.',
                'count' => $approachingMbt,
                'icon' => 'fas fa-hourglass-half',
                'url' => route('admin.mutasi.index', ['tab' => 'mpp']),
            ];
        }

        return $items;
    }

    /** Total badge value: the number of items needing action, not the sum of their rows. */
    public function totalFor(User $user): int
    {
        return count($this->itemsFor($user));
    }

    private function approachingMbtCount(): int
    {
        $today = today();

        return Employee::aktif()
            ->whereNotNull('tanggal_mbt')
            ->whereDate('tanggal_mbt', '>', $today)
            ->whereDate('tanggal_mbt', '<=', $today->copy()->addMonthsNoOverflow(KepegawaianPolicy::MBT_PERINGATAN_BULAN))
            ->count();
    }
}
