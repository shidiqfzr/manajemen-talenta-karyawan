<?php

// Data uji E2E: keadaan tata kelola lain (pengajuan oleh admin lain dan oleh admin yang login, ditolak, kedaluwarsa, dibatalkan).
use App\Models\EmployeeImportLog;
use App\Models\User;

$other = User::factory()->create(['role' => 'admin', 'name' => 'Rina Pengaju']);

$make = fn (array $overrides) => EmployeeImportLog::create($overrides + [
    'user_id' => $other->id, 'user_name' => $other->name, 'file_name' => 'pengajuan.xlsx', 'file_size' => 4096,
    'file_sha256' => hash('sha256', uniqid()), 'total_rows' => 40, 'created_count' => 0, 'updated_count' => 0,
    'unchanged_count' => 0, 'skipped_count' => 0, 'has_errors' => false, 'token' => uniqid('e2e-'),
]);

$make(['file_name' => 'pengajuan_rina.xlsx', 'status' => EmployeeImportLog::STATUS_PENDING, 'submitted_at' => now()->subHour()]);
$make(['file_name' => 'pengajuan_admin.xlsx', 'user_id' => 1, 'user_name' => 'Admin User', 'status' => EmployeeImportLog::STATUS_PENDING, 'submitted_at' => now()->subHour()]);
$make([
    'file_name' => 'ditolak.xlsx', 'status' => EmployeeImportLog::STATUS_REJECTED, 'submitted_at' => now()->subDay(),
    'checker_id' => 1, 'checker_name' => 'Admin User', 'decided_at' => now()->subHours(20),
    'decision_note' => 'Kolom unit kerja belum sesuai Master Formasi.',
]);
$make([
    'file_name' => 'kedaluwarsa.xlsx', 'status' => EmployeeImportLog::STATUS_EXPIRED, 'submitted_at' => now()->subDays(9),
    'decided_at' => now()->subDays(2), 'decision_note' => 'Tidak diputuskan dalam 7 hari.',
]);
$make([
    'file_name' => 'dibatalkan.xlsx', 'status' => EmployeeImportLog::STATUS_ROLLED_BACK, 'created_count' => 12, 'total_rows' => 12,
    'committed_at' => now()->subDays(2), 'rolled_back_at' => now()->subDay(), 'rolled_back_by' => 1, 'rolled_back_by_name' => 'Admin User',
    'rollback_reason' => 'Berkas salah periode, diganti berkas terbaru.',
]);
