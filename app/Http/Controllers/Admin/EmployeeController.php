<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EmployeeTemplateExport;
use App\Exports\FailedEmployeesExport;
use App\Exports\ImportSummaryExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommitEmployeeImportRequest;
use App\Http\Requests\Admin\ImportEmployeeFileRequest;
use App\Http\Requests\Admin\ImportTokenRequest;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use App\Services\EmployeeExportService;
use App\Services\EmployeeImportService;
use App\Services\Import\EmployeeImportDictionary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    // Display all employees
    public function index(Request $request)
    {
        $query = Employee::with(['concurrentJobs']);

        // Text search (Omnisearch: NIK, Nama, Jabatan, Unit Kerja via Eloquent Scope)
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Dropdown filters
        if ($request->filled('jabatan')) {
            $query->where('jabatan', $request->jabatan);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('unit_kerja')) {
            $query->where('unit_kerja', $request->unit_kerja);
        }

        if ($request->filled('rm_level')) {
            $query->where('rm_level', $request->rm_level);
        }

        if ($request->filled('golongan')) {
            $query->where('golongan', $request->golongan);
        }

        // Filter: Status Penugasan (Definitif vs Plt/Pjs/Diperbantukan)
        if ($request->filled('status_penugasan')) {
            $status = $request->status_penugasan;
            if ($status === 'sementara') {
                $query->whereHas('concurrentJobs');
            } elseif ($status === 'definitif') {
                $query->whereDoesntHave('concurrentJobs');
            } else {
                $query->whereHas('concurrentJobs', function ($q) use ($status) {
                    $q->where('status_penugasan', $status);
                });
            }
        }

        // Filter: Status Khusus / Tata Kelola (Sanksi, Overflow, Rangkap)
        if ($request->filled('status_khusus')) {
            $khusus = $request->status_khusus;
            if ($khusus === 'sanksi') {
                $query->sanctionAll();
            } elseif ($khusus === 'overflow') {
                $query->overflow();
            } elseif ($khusus === 'rangkap') {
                $query->whereHas('concurrentJobs');
            }
        }

        // Period filter: Tahun Masuk (TMT Bekerja)
        if ($request->filled('tahun_masuk')) {
            $query->whereYear('tmt_bekerja', $request->tahun_masuk);
        }

        // Period filter: Bulan Masuk (TMT Bekerja)
        if ($request->filled('bulan_masuk')) {
            $query->whereMonth('tmt_bekerja', $request->bulan_masuk);
        }

        // Fixed Global Headcount KPI Metrics (Executive Overview)
        $totalEmployees = Employee::count();
        $totalUnits = Employee::whereNotNull('unit_kerja')->where('unit_kerja', '!=', '')->distinct()->count('unit_kerja');
        $karpimCount = Employee::karpim()->count();
        $pelaksanaCount = Employee::pelaksana()->count();

        // Global reference
        $globalTotalEmployees = $totalEmployees;
        $penugasanSementaraCount = Employee::whereHas('concurrentJobs')->count();

        // Special governance metrics
        $sanksiAktifCount = Employee::sanctionActive()->count();
        $sanksiTotalCount = Employee::sanctionAll()->count();
        $overflowCount = Employee::overflow()->count();

        // Column sorting (Enterprise HRIS standard: sortable table columns)
        $sortColumn = $request->query('sort', 'nama');
        $sortDirection = $request->query('direction', 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['nama', 'jabatan', 'rm_level', 'level', 'golongan'];

        if (in_array($sortColumn, $allowedSorts)) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy('nama', 'asc');
        }

        $employees = $query->paginate(20)->withQueryString();

        // Get unique sorted values for filters
        $jabatans = Employee::select('jabatan')->whereNotNull('jabatan')->where('jabatan', '!=', '')->distinct()->orderBy('jabatan')->pluck('jabatan');
        $levels = Employee::select('level')->whereNotNull('level')->where('level', '!=', '')->distinct()->orderBy('level')->pluck('level');
        $units = Employee::select('unit_kerja')->whereNotNull('unit_kerja')->where('unit_kerja', '!=', '')->distinct()->orderBy('unit_kerja')->pluck('unit_kerja');
        $golongans = Employee::select('golongan')->whereNotNull('golongan')->where('golongan', '!=', '')->distinct()->orderBy('golongan')->pluck('golongan');
        $rmLevels = Employee::RM_LEVELS;

        // Dynamic hire years from database (Fast substring extraction)
        $availableYears = Employee::whereNotNull('tmt_bekerja')
            ->pluck('tmt_bekerja')
            ->map(fn ($date) => $date ? substr((string) $date, 0, 4) : null)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        $months = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
            '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];

        // Fetch employees with expired sanctions (Diperban)
        $expiredSanksiEmployees = Employee::sanctionExpired()
            ->orderBy('tanggal_berakhir_sanksi', 'asc')
            ->get();

        return view('admin.employees.index', compact(
            'employees',
            'jabatans',
            'levels',
            'units',
            'golongans',
            'rmLevels',
            'availableYears',
            'months',
            'totalEmployees',
            'totalUnits',
            'karpimCount',
            'pelaksanaCount',
            'globalTotalEmployees',
            'expiredSanksiEmployees',
            'penugasanSementaraCount',
            'sanksiAktifCount',
            'sanksiTotalCount',
            'overflowCount'
        ));
    }

    // Show form to create new employee (can be pre-filled from vacancy)
    public function create(Request $request)
    {
        $prefill = [
            'jabatan' => $request->query('jabatan'),
            'unit_kerja' => $request->query('unit_kerja'),
            'level' => $request->query('level'),
            'golongan' => $request->query('golongan'),
        ];

        $activeUnits = Unit::active()->orderBy('urutan')->orderBy('nama')->get();
        if ($activeUnits->isNotEmpty()) {
            $groupedUnits = $activeUnits->groupBy('wilayah')
                ->map(fn ($units) => $units->pluck('nama')->toArray())
                ->toArray();
        } else {
            $groupedUnits = Employee::GROUPED_UNITS;
        }

        $activePositions = Position::active()->orderBy('nama')->pluck('nama')->toArray();
        $religions = Employee::RELIGIONS;
        $genders = Employee::GENDERS;
        $levels = Employee::LEVELS;
        $familyStatuses = Employee::FAMILY_STATUSES;
        $educationLevels = Employee::EDUCATION_LEVELS;
        $entryChannels = Employee::ENTRY_CHANNELS;
        $rmLevels = Employee::RM_LEVELS;

        return view('admin.employees.add-employee', compact(
            'prefill',
            'groupedUnits',
            'activePositions',
            'religions',
            'genders',
            'levels',
            'familyStatuses',
            'educationLevels',
            'entryChannels',
            'rmLevels'
        ));
    }

    // Store new employee
    public function store(StoreEmployeeRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('photos', 'public');
        }

        // Standardize data (Uppercase nama & trim unit_kerja)
        $validated['nama'] = strtoupper(trim($validated['nama']));
        $validated['unit_kerja'] = trim($validated['unit_kerja']);
        $validated['jalur_masuk'] = $validated['jalur_masuk'] ?? 'Reguler';
        $validated['status_penugasan'] = $validated['status_penugasan'] ?? 'Definitif';

        // Validasi ketersediaan formasi (Jabatan harus ada di Unit Kerja dengan Level yang sesuai)
        $unit = Unit::where('nama', $validated['unit_kerja'])->first();
        if ($unit) {
            $validPositions = $unit->positions()
                ->where('positions.is_active', true)
                ->where('positions.level', $validated['level'])
                ->pluck('positions.nama')
                ->toArray();

            // Allow if position list is empty (fallback to master data skip if mapping incomplete)
            // or if the position is explicitly valid
            if (count($validPositions) > 0 && ! in_array($validated['jabatan'], $validPositions)) {
                return back()->withErrors(['jabatan' => 'Jabatan Formasi tidak valid untuk Unit Kerja dan Level Strata Pegawai yang dipilih.'])->withInput();
            }
        }

        // Atomic transaction: create employee (EmployeeObserver automatically registers initial job assignment)
        $employee = DB::transaction(function () use ($validated, $request) {
            $emp = Employee::create($validated);

            // If an explicit SK number was provided in form, update the initial job history
            if ($request->filled('nomor_sk')) {
                $initialJob = $emp->jobHistories()->whereNull('tmt_akhir')->first();
                if ($initialJob) {
                    $initialJob->update([
                        'nomor_sk' => $request->input('nomor_sk'),
                    ]);
                }
            }

            return $emp;
        });

        return redirect()->route('admin.employees.index')->with('success', "Pegawai baru ({$employee->nama}) berhasil ditambahkan dan formasi jabatan telah terisi.");
    }

    public function show($nik)
    {
        $employee = Employee::where('nik', $nik)->firstOrFail();

        $histories = $employee->jobHistories()->paginate(10);
        $trainings = $employee->trainings()->paginate(10);
        $evaluations = $employee->evaluations()->latest()->paginate(5);

        return view('admin.employees.show', compact('employee', 'histories', 'trainings', 'evaluations'));
    }

    /**
     * Tampilkan halaman khusus Wizard Impor Data Pegawai (Workday / SAP Multi-Step Standard).
     */
    public function importPage()
    {
        $dictionaryCategories = EmployeeImportDictionary::getCategories();

        return view('admin.employees.import', compact('dictionaryCategories'));
    }

    public function import(ImportEmployeeFileRequest $request, EmployeeImportService $importService)
    {
        $summary = $importService->directImport(
            $request->file('import_file'),
            auth()->user(),
            $request->ip(),
            $request->userAgent()
        );

        return redirect()
            ->route('admin.employees.index')
            ->with([
                'success' => "{$summary['created']} data baru ditambahkan, {$summary['updated']} data diperbarui, ".($summary['unchanged'] ?? 0)." data tetap/identik, {$summary['skipped']} baris dilewati.",
                'import_summary' => $summary,
            ]);
    }

    public function previewImport(ImportEmployeeFileRequest $request, EmployeeImportService $importService)
    {
        $previewData = $importService->preview($request->file('import_file'));

        return response()->json([
            'success' => true,
            ...$previewData,
        ]);
    }

    public function commitImport(CommitEmployeeImportRequest $request, EmployeeImportService $importService)
    {
        try {
            $summary = $importService->commit(
                $request->token,
                auth()->user(),
                $request->ip(),
                $request->userAgent()
            );

            if ($summary['is_async'] ?? false) {
                return response()->json([
                    'success' => true,
                    'is_async' => true,
                    'token' => $request->token,
                    'message' => $summary['message'],
                ]);
            }

            session()->flash('success', "{$summary['created']} data baru ditambahkan, {$summary['updated']} data diperbarui, ".($summary['unchanged'] ?? 0)." data tetap/identik, {$summary['skipped']} baris dilewati.");
            session()->flash('import_summary', $summary);

            return response()->json([
                'success' => true,
                'is_async' => false,
                'redirect_url' => route('admin.employees.index'),
                'summary' => $summary,
            ]);
        } catch (\DomainException|\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function importStatus(Request $request, string $token, EmployeeImportService $importService)
    {
        // Fail-Safe SLA: Auto-fallback ke eksekusi sinkron jika worker antrean tidak merespons dalam 5 detik
        if ($request->boolean('fallback_sync')) {
            try {
                $result = $importService->fallbackToSync(
                    $token,
                    auth()->user(),
                    $request->ip(),
                    $request->userAgent()
                );

                if (($result['status'] ?? '') === 'completed' && isset($result['summary']) && isset($result['token'])) {
                    $result['redirect_url'] = route('admin.employees.index');
                }

                return response()->json([
                    'success' => true,
                    ...$result,
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengalihkan ke mode sinkron: '.$e->getMessage(),
                ], 422);
            }
        }

        $status = $importService->getJobStatus($token);

        if (! $status) {
            return response()->json([
                'success' => false,
                'message' => 'Status antrean tidak ditemukan atau sesi telah berakhir.',
            ], 404);
        }

        // Jika job commit selesai, persiapkan flash session untuk tampilan index
        if (($status['status'] ?? '') === 'completed' && ($status['type'] ?? '') === 'commit' && isset($status['summary'])) {
            $summary = $status['summary'];
            session()->flash('success', "{$summary['created']} data baru ditambahkan, {$summary['updated']} data diperbarui, ".($summary['unchanged'] ?? 0)." data tetap/identik, {$summary['skipped']} baris dilewati.");
            session()->flash('import_summary', $summary);
            $status['redirect_url'] = route('admin.employees.index');
        }

        return response()->json([
            'success' => true,
            ...$status,
        ]);
    }

    public function downloadFailedRows(ImportTokenRequest $request, EmployeeImportService $importService)
    {
        $failedRows = $importService->getFailedRows($request->token);
        if (empty($failedRows)) {
            return redirect()->back()->with('error', 'Tidak ada data baris gagal yang dapat diunduh atau sesi telah berakhir.');
        }

        $filename = 'baris_gagal_impor_karyawan_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new FailedEmployeesExport($failedRows), $filename);
    }

    public function downloadImportReport(ImportTokenRequest $request, EmployeeImportService $importService)
    {
        $reportData = $importService->getImportReport($request->token);
        if (empty($reportData)) {
            return redirect()->back()->with('error', 'Data rekapitulasi impor tidak ditemukan atau sesi telah berakhir.');
        }

        $filename = 'rekap_laporan_impor_karyawan_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new ImportSummaryExport($reportData), $filename);
    }

    public function export(Request $request, EmployeeExportService $exportService)
    {
        return $exportService->exportExcel($request, auth()->user());
    }

    public function downloadTemplate()
    {
        return Excel::download(new EmployeeTemplateExport, 'karyawan_import_template.xlsx');
    }

    // Edit existing employee
    public function edit($nik)
    {
        $employee = Employee::findOrFail($nik);

        $activeUnits = Unit::active()->orderBy('urutan')->orderBy('nama')->get();
        if ($activeUnits->isNotEmpty()) {
            $groupedUnits = $activeUnits->groupBy('wilayah')
                ->map(fn ($units) => $units->pluck('nama')->toArray())
                ->toArray();
        } else {
            $groupedUnits = Employee::GROUPED_UNITS;
        }

        $activePositions = Position::active()->orderBy('nama')->pluck('nama')->toArray();
        $religions = Employee::RELIGIONS;
        $genders = Employee::GENDERS;
        $levels = Employee::LEVELS;
        $familyStatuses = Employee::FAMILY_STATUSES;
        $educationLevels = Employee::EDUCATION_LEVELS;
        $entryChannels = Employee::ENTRY_CHANNELS;
        $rmLevels = Employee::RM_LEVELS;

        return view('admin.employees.edit-employee', compact(
            'employee',
            'groupedUnits',
            'activePositions',
            'religions',
            'genders',
            'levels',
            'familyStatuses',
            'educationLevels',
            'entryChannels',
            'rmLevels'
        ));
    }

    // Update existing employee
    public function update(UpdateEmployeeRequest $request, $nik)
    {
        $employee = Employee::findOrFail($nik);

        $validated = $request->validated();

        if ($request->hasFile('foto')) {
            // Delete old foto if exists
            if ($employee->foto && Storage::disk('public')->exists($employee->foto)) {
                Storage::disk('public')->delete($employee->foto);
            }

            $validated['foto'] = $request->file('foto')->store('photos', 'public');
        } else {
            $validated['foto'] = $employee->foto;
        }

        // Standardize data (Uppercase nama & trim unit_kerja)
        $validated['nama'] = strtoupper(trim($validated['nama']));
        $validated['status_penugasan'] = $validated['status_penugasan'] ?? 'Definitif';

        DB::transaction(function () use ($employee, $validated) {
            $employee->update($validated);
        });

        return redirect()->route('admin.employees.index')->with('success', 'Data pegawai berhasil diperbarui.');
    }

    // Delete employee
    public function destroy($nik)
    {
        $employee = Employee::findOrFail($nik);

        DB::transaction(function () use ($employee) {
            // Delete foto if exists
            if ($employee->foto && Storage::disk('public')->exists($employee->foto)) {
                Storage::disk('public')->delete($employee->foto);
            }

            $employee->delete();
        });

        return redirect()->route('admin.employees.index')->with('success', 'Data pegawai berhasil dihapus.');
    }

    /**
     * Hapus massal data pegawai terpilih secara transaksional.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'niks' => 'required|array|min:1',
            'niks.*' => 'required|string|size:8',
        ]);

        $count = count($validated['niks']);

        DB::transaction(function () use ($validated) {
            $employees = Employee::whereIn('nik', $validated['niks'])->get();
            foreach ($employees as $employee) {
                if ($employee->foto && Storage::disk('public')->exists($employee->foto)) {
                    Storage::disk('public')->delete($employee->foto);
                }
                $employee->delete();
            }
        });

        return redirect()->route('admin.employees.index')->with('success', "{$count} data pegawai terpilih berhasil dihapus.");
    }
}
