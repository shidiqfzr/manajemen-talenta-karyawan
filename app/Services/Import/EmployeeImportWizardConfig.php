<?php

namespace App\Services\Import;

use App\Services\EmployeeImportGovernanceService;

/**
 * Konfigurasi yang diserahkan halaman Impor Data Karyawan kepada komponen Alpine `employeeImportWizard`
 * (resources/js/admin/employee-import-wizard.js): izin persetujuan, kamus kolom, URL rute, dan token CSRF.
 * Satu tempat untuk nilai dari server, sehingga skrip halaman tidak menulis URL atau data sendiri dan tidak perlu Blade.
 */
final class EmployeeImportWizardConfig
{
    /** Penanda pada URL status yang diganti token sesi impor oleh komponen JavaScript. */
    public const TOKEN_PLACEHOLDER = '__TOKEN__';

    /**
     * @return array{requiresApproval: bool, columns: list<array<string, mixed>>, urls: array{preview: string, commit: string, status: string}, csrf: string}
     */
    public static function make(): array
    {
        return [
            'requiresApproval' => EmployeeImportGovernanceService::requiresApproval(),
            'columns' => EmployeeImportDictionary::getColumns(),
            'urls' => [
                'preview' => route('admin.employees.import.preview'),
                'commit' => route('admin.employees.import.commit'),
                'status' => route('admin.employees.import.status', ['token' => self::TOKEN_PLACEHOLDER]),
            ],
            'csrf' => csrf_token(),
        ];
    }
}
