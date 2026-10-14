<?php

namespace App\Services;

use App\Exports\EmployeesExport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeExportService
{
    /**
     * Mengekspor data pegawai ke format Excel (.xlsx) berstandar enterprise PTPN IV
     * dengan pencatatan Audit Trail sesuai UU No. 27/2022 (UU PDP).
     */
    public function exportExcel(Request|array $requestOrFilters = [], ?User $user = null): BinaryFileResponse
    {
        $filters = $this->sanitizeFilters($requestOrFilters);

        $currentUser = $user ?? auth()->user();

        $export = new EmployeesExport($filters, $currentUser);

        $recordCount = $export->query()->count();

        // Audit Trail Pencatatan Ekspor Data Pegawai (UU PDP No. 27/2022 & Tata Kelola BUMN)
        $ipAddress = ($requestOrFilters instanceof Request) ? $requestOrFilters->ip() : request()->ip();
        $userAgent = ($requestOrFilters instanceof Request) ? $requestOrFilters->userAgent() : request()->userAgent();

        Log::info('HRIS Export Data Pegawai dieksekusi', [
            'user_id' => $currentUser?->id,
            'user_name' => $currentUser?->name ?? 'System',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'filters' => array_filter($filters),
            'record_count' => $recordCount,
            'timestamp' => now()->toIso8601String(),
        ]);

        $filename = $this->generateFilename();

        return Excel::download($export, $filename);
    }

    /**
     * Sanitasi dan ekstraksi filter pencarian yang diizinkan.
     */
    public function sanitizeFilters(Request|array $requestOrFilters): array
    {
        $allowed = [
            'search',
            'jabatan',
            'level',
            'rm_level',
            'unit_kerja',
            'golongan',
            'status_penugasan',
            'status_khusus',
            'tahun_masuk',
            'bulan_masuk',
            'niks',
        ];

        if ($requestOrFilters instanceof Request) {
            return $requestOrFilters->only($allowed);
        }

        return array_intersect_key($requestOrFilters, array_flip($allowed));
    }

    /**
     * Menghasilkan nama file standar dengan stempel waktu dinamis.
     */
    public function generateFilename(): string
    {
        return 'data_karyawan_ptpn4_reg5_'.now()->format('Ymd_His').'.xlsx';
    }
}
