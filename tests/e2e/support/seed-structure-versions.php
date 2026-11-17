<?php

// Data uji E2E untuk Versi Struktur: SK kedua yang sudah diterapkan (1 Okt 2026) dan dua karyawan dengan riwayat jabatan yang
// melintasi kedua versi. Snapshot SK kedua turunan dari SK baseline (satu kuota berubah, satu unit baru); basis data struktur
// sekarang sengaja tidak diubah, sehingga perbandingan dengan "struktur sekarang" menampilkan selisih.
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\StructureDecree;

$baseline = StructureDecree::where('status', StructureDecree::STATUS_APPLIED)->orderBy('id')->firstOrFail();
$units = $baseline->snapshot['units'];
$names = array_keys($units);
[$unitA, $unitB] = [$names[0], $names[1]];
$seatA = array_key_first($units[$unitA]['seats']);
$seatB = array_key_first($units[$unitB]['seats']);

$second = $units;
$second[$unitA]['seats'][$seatA]['kuota'] += 1;
$second['Unit Uji Versi Baru'] = ['kode' => 'Bagian', 'wilayah' => $units[$unitA]['wilayah'], 'urutan' => 999, 'is_active' => true, 'seats' => [
    'Kepala Uji' => ['rm_level' => 'RM-2', 'level' => 'Karpim', 'bidang' => 'UMU', 'kuota' => 1],
]];

StructureDecree::create([
    'sk_number' => 'DPSB/SKPTS/401/X/2026',
    'title' => 'Penyesuaian Struktur Organisasi Region Office (data uji)',
    'sk_date' => '2026-10-01',
    'effective_date' => '2026-10-01',
    'status' => StructureDecree::STATUS_APPLIED,
    'source_path' => 'e2e/401.xlsx',
    'source_sha256' => hash('sha256', 'e2e-sk-401'),
    'applied_at' => '2026-10-01 08:00:00',
    'snapshot' => ['units' => $second],
]);

$person = function (string $nik, string $nama, string $unit, string $seat): void {
    Employee::factory()->create(['nik' => $nik, 'nama' => $nama, 'unit_kerja' => $unit, 'jabatan' => $seat]);
    JobHistory::withTrashed()->where('employee_nik', $nik)->forceDelete();
};
$place = fn (string $nik, string $unit, string $seat, string $from, ?string $until) => JobHistory::create([
    'employee_nik' => $nik, 'unit_kerja' => $unit, 'jabatan' => $seat, 'tmt_awal' => $from, 'tmt_akhir' => $until,
    'is_applied' => true, 'sumber' => JobHistory::SUMBER_MUTASI, 'nomor_sk' => $until ? 'SK/MUTASI/UJI/1' : null,
]);

$person('90000001', 'Budi Uji Versi', $unitB, $seatB);
$place('90000001', $unitA, $seatA, '2026-09-01', '2026-09-30');
$place('90000001', $unitB, $seatB, '2026-10-01', null);

$person('90000002', 'Sari Uji Versi', $unitA, $seatA);
$place('90000002', $unitA, $seatA, '2026-09-01', null);
