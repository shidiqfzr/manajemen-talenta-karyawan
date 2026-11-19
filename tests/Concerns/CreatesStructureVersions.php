<?php

namespace Tests\Concerns;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\StructureDecree;

/**
 * Data uji untuk versi struktur (SK Diterapkan berisi snapshot) dan riwayat jabatan karyawan. Satu sumber untuk uji
 * StructureAsOfService, EmployeePositionTimelineService, dan layar Versi Struktur.
 */
trait CreatesStructureVersions
{
    /** SK dengan snapshot ($snapshot berbentuk ['units' => [...]]); bawaan berstatus Diterapkan. */
    private function appliedDecree(string $number, string $effective, ?array $snapshot, string $status = StructureDecree::STATUS_APPLIED): StructureDecree
    {
        return StructureDecree::create([
            'sk_number' => $number,
            'title' => 'Perubahan Struktur Organisasi',
            'sk_date' => $effective,
            'effective_date' => $effective,
            'status' => $status,
            'source_path' => 'decrees/'.md5($number).'.xlsx',
            'source_sha256' => str_repeat('a', 64),
            'snapshot' => $snapshot,
            'applied_at' => $status === StructureDecree::STATUS_APPLIED ? now() : null,
        ]);
    }

    /** Snapshot satu unit berisi satu jabatan (RM-1, Karpim, TAN). @return array{units: array<string, array<string, mixed>>} */
    private function seatSnapshot(string $unit, string $jabatan, int $kuota = 1): array
    {
        return ['units' => [$unit => $this->snapshotUnit($jabatan, $kuota)]];
    }

    /** Satu unit snapshot dengan satu jabatan. @return array<string, mixed> */
    private function snapshotUnit(string $jabatan, int $kuota = 1): array
    {
        return ['kode' => 'Bagian', 'wilayah' => 'Wilayah X', 'urutan' => 1, 'is_active' => true, 'seats' => [$jabatan => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => $kuota]]];
    }

    /** Karyawan tanpa riwayat: EmployeeObserver menulis baris data_awal saat dibuat, jadi dihapus agar uji mengatur riwayatnya sendiri. */
    private function employeeWithoutHistory(string $nik, string $nama, array $attributes = []): Employee
    {
        $employee = Employee::factory()->create(['nik' => $nik, 'nama' => $nama] + $attributes);
        JobHistory::withTrashed()->where('employee_nik', $nik)->forceDelete();

        return $employee;
    }

    /** Penempatan yang sudah berlaku ($until null = masih menjabat). */
    private function placement(string $nik, string $unit, string $jabatan, string $from, ?string $until = null, array $extra = []): JobHistory
    {
        return JobHistory::create($extra + [
            'employee_nik' => $nik, 'unit_kerja' => $unit, 'jabatan' => $jabatan, 'tmt_awal' => $from, 'tmt_akhir' => $until,
            'is_applied' => true, 'sumber' => JobHistory::SUMBER_MUTASI,
        ]);
    }
}
