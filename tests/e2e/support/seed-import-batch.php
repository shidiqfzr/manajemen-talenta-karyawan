<?php

// Data uji E2E: satu impor tersimpan sebagian dengan alasan baris dilewati yang realistis (tanpa data pribadi).
use App\Models\Employee;
use App\Models\EmployeeImportLog;

$errors = [];
$templates = [
    [44, 'Jabatan karyawan wajib diisi.'],
    [18, "Kuota formasi jabatan 'Asisten Kepala' pada Unit Kerja 'Kebun Tajati' sudah terpenuhi (3 formasi)."],
    [1, 'NIK wajib diisi.'],
    [1, 'TMT Bekerja minimal 15 tahun setelah Tanggal Lahir.'],
    [1, "NIK '88••••01' terdeteksi ganda (duplikat dari baris 12 pada berkas yang sama)."],
];
$row = 3;
foreach ($templates as [$count, $message]) {
    for ($i = 0; $i < $count; $i++) {
        $errors[] = ['row' => $row += 5, 'errors' => [$message]];
    }
}

$log = EmployeeImportLog::create([
    'user_id' => 1,
    'user_name' => 'Admin User',
    'user_email' => 'admin@example.com',
    'file_name' => 'karyawan_import_bez_oktober_2026.xlsx',
    'file_size' => 87_860,
    'file_sha256' => hash('sha256', 'e2e-fixture'),
    'total_rows' => 384,
    'created_count' => 319,
    'updated_count' => 0,
    'unchanged_count' => 0,
    'skipped_count' => 65,
    'has_errors' => true,
    'status' => EmployeeImportLog::STATUS_COMPLETED,
    'committed_at' => now(),
    'token' => 'e2e-token',
    'metadata' => ['summary' => ['errors' => $errors]],
]);

// Rekaman perubahan agar kartu pembatalan berada pada keadaan "siap dibatalkan".
foreach (Employee::factory()->count(5)->create() as $employee) {
    $log->changes()->create([
        'action' => 'CREATE',
        'nik' => $employee->nik,
        'after_hash' => Employee::stateHash(Employee::findOrFail($employee->nik)),
    ]);
}
