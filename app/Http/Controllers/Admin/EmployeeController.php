<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ImportAbortedException;
use App\Exports\EmployeeTemplateExport;
use App\Exports\FailedEmployeesWorkbook;
use App\Exports\ImportSummaryExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommitEmployeeImportRequest;
use App\Http\Requests\Admin\ImportEmployeeFileRequest;
use App\Http\Requests\Admin\ImportTokenRequest;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeeImportLog;
use App\Models\Position;
use App\Models\Unit;
use App\Services\EmployeeDeletionService;
use App\Services\EmployeeExportService;
use App\Services\EmployeeImportService;
use App\Services\FormasiSeatGuard;
use App\Services\Import\EmployeeImportDictionary;
use App\Services\PensiunService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    // Display all employees: page-level KPI metrics and the expired-sanction notice. The directory list itself
    // (search, filters, sort, pagination) and the KPI cards that drive it are the Livewire component
    // App\Livewire\Admin\Employees\EmployeeTable, which receives these metrics as `kpi`.
    public function index()
    {
        // Fetch employees with expired sanctions (Diperban)
        $expiredSanksiEmployees = Employee::aktif()->sanctionExpired()
            ->orderBy('tanggal_berakhir_sanksi', 'asc')
            ->get();

        // Fixed Global Headcount KPI Metrics (Executive Overview). The Karpim/Karpel counts use the same
        // equality condition as the table's `level` filter, so a card's count matches the rows it shows.
        $kpi = [
            'total' => Employee::aktif()->count(),
            'units' => Employee::aktif()->whereNotNull('unit_kerja')->where('unit_kerja', '!=', '')->distinct()->count('unit_kerja'),
            'karpim' => Employee::aktif()->where('level', Employee::LEVEL_KARPIM)->count(),
            'karpel' => Employee::aktif()->where('level', Employee::LEVEL_KARPEL)->count(),
            // Special governance metrics
            'rangkap' => Employee::aktif()->whereHas('concurrentJobs')->count(),
            'sanksi' => Employee::aktif()->sanctionActive()->count(),
            'overflow' => Employee::aktif()->overflow()->count(),
            'expired' => $expiredSanksiEmployees->count(),
        ];

        return view('admin.employees.index', compact('kpi', 'expiredSanksiEmployees'));
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
        $rmLevels = Position::RM_LEVEL_LIST;

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

    /**
     * Pemeriksaan ketersediaan NIK saat pengisian form. Hanya mengembalikan status dan tautan,
     * tanpa data pribadi pemilik NIK (UU PDP).
     */
    public function checkNik(Request $request)
    {
        $nik = (string) $request->query('nik', '');

        if (! preg_match('/^[0-9]{8}$/', $nik)) {
            return response()->json(['valid' => false, 'exists' => false], 422);
        }

        $exists = Employee::where('nik', $nik)->exists();

        return response()->json([
            'valid' => true,
            'exists' => $exists,
            'url' => $exists ? route('admin.employees.show', $nik) : null,
        ]);
    }

    // Store new employee
    public function store(StoreEmployeeRequest $request, FormasiSeatGuard $seats)
    {
        $validated = $request->validated();

        // Standardize data (Uppercase nama & trim unit_kerja)
        $validated['nama'] = strtoupper(trim($validated['nama']));
        $validated['unit_kerja'] = trim($validated['unit_kerja']);
        $validated['jalur_masuk'] = $validated['jalur_masuk'] ?? 'Reguler';
        $validated['status_penugasan'] = $validated['status_penugasan'] ?? 'Definitif';

        // Atomic transaction: create employee (EmployeeObserver automatically registers initial job assignment)
        $seatError = null;
        $employee = DB::transaction(function () use ($validated, $request, $seats, &$seatError) {
            // Position control: kursi aktif + kuota diperiksa di dalam transaksi yang sama dengan penyimpanan
            $seatError = $seats->check($validated['unit_kerja'], $validated['jabatan'], $validated['level'], $validated['status_penugasan']);
            if ($seatError !== null) {
                return null;
            }

            if ($request->hasFile('foto')) {
                $validated['foto'] = $request->file('foto')->store('photos', 'public');
            }

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

        if ($seatError !== null) {
            return back()->withErrors(['jabatan' => $seatError])->withInput();
        }

        return redirect()->route('admin.employees.index')->with('success', "Karyawan baru ({$employee->nama}) berhasil ditambahkan dan formasi jabatan telah terisi.");
    }

    public function show($nik)
    {
        $employee = Employee::where('nik', $nik)->firstOrFail();

        $histories = $employee->jobHistories()->paginate((int) config('hris.pagination.default'));
        $trainings = $employee->trainings()->paginate((int) config('hris.pagination.default'));
        $evaluations = $employee->evaluations()->latest()->paginate((int) config('hris.pagination.default'));

        // Dampak pemberhentian yang ditampilkan pada modal sebelum eksekusi (DESIGN.md §7.2).
        $separationImpact = [
            'active_jobs' => $employee->activeJobs()->count(),
            'scheduled' => $employee->scheduledJobs()->count(),
            'pensiun' => $employee->effective_tanggal_pensiun?->toDateString(),
        ];

        return view('admin.employees.show', compact('employee', 'histories', 'trainings', 'evaluations', 'separationImpact'));
    }

    /**
     * Tampilkan halaman khusus Wizard Impor Data Karyawan (Workday / SAP Multi-Step Standard).
     */
    public function importPage()
    {
        $dictionaryCategories = EmployeeImportDictionary::getCategories();
        $pendingApprovalCount = EmployeeImportLog::where('status', EmployeeImportLog::STATUS_PENDING)->count();

        return view('admin.employees.import', compact('dictionaryCategories', 'pendingApprovalCount'));
    }

    public function import(ImportEmployeeFileRequest $request, EmployeeImportService $importService)
    {
        try {
            $summary = $importService->directImport(
                $request->file('import_file'),
                auth()->user(),
                $request->ip(),
                $request->userAgent()
            );
        } catch (ImportAbortedException $e) {
            return back()->withErrors(['import_file' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.employees.index')
            ->with([
                'success' => "{$summary['created']} data baru ditambahkan, {$summary['updated']} data diperbarui, ".($summary['unchanged'] ?? 0)." data tetap/identik, {$summary['skipped']} baris dilewati.",
                'import_summary' => $summary,
            ]);
    }

    public function previewImport(ImportEmployeeFileRequest $request, EmployeeImportService $importService)
    {
        try {
            $previewData = $importService->preview($request->file('import_file'), auth()->user());
        } catch (ImportAbortedException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            ...$previewData,
        ]);
    }

    public function commitImport(CommitEmployeeImportRequest $request, EmployeeImportService $importService)
    {
        $this->authorizeImportToken($request->token, $importService);

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

            if ($summary['pending_approval'] ?? false) {
                return response()->json([
                    'success' => true,
                    'is_async' => false,
                    'pending_approval' => true,
                    'batch_url' => route('admin.employees.import.batches.show', $summary['batch_id']),
                    'summary' => $summary['summary'],
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
        } catch (ImportAbortedException|\DomainException|\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Sesi impor (pratinjau, status, laporan, baris gagal) hanya dapat diakses pengunggahnya.
     */
    private function authorizeImportToken(string $token, EmployeeImportService $importService): void
    {
        abort_unless($importService->isTokenOwnedBy($token, auth()->user()), 403, 'Sesi impor ini dimulai oleh administrator lain.');
    }

    public function importStatus(Request $request, string $token, EmployeeImportService $importService)
    {
        $this->authorizeImportToken($token, $importService);

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
        $this->authorizeImportToken($request->token, $importService);

        $failedRows = $importService->getFailedRows($request->token);
        if (empty($failedRows)) {
            return redirect()->back()->with('error', 'Tidak ada data baris gagal yang dapat diunduh atau sesi telah berakhir.');
        }

        $filename = 'baris_gagal_impor_karyawan_'.now()->format('Ymd_His').'.xlsx';

        $importService->recordDownloadAudit('baris_gagal', $request->token, auth()->user(), $request->ip(), count($failedRows));

        return Excel::download(new FailedEmployeesWorkbook($failedRows, auth()->user()?->name ?? 'Administrator', now()->translatedFormat('d F Y H:i:s')), $filename);
    }

    public function downloadImportReport(ImportTokenRequest $request, EmployeeImportService $importService)
    {
        $this->authorizeImportToken($request->token, $importService);

        $reportData = $importService->getImportReport($request->token);
        if (empty($reportData)) {
            return redirect()->back()->with('error', 'Data rekapitulasi impor tidak ditemukan atau sesi telah berakhir.');
        }

        $filename = 'rekap_laporan_impor_karyawan_'.now()->format('Ymd_His').'.xlsx';

        $importService->recordDownloadAudit('rekap_impor', $request->token, auth()->user(), $request->ip(), count($reportData['rows'] ?? []) + count($reportData['failed_rows'] ?? []));

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
        $rmLevels = Position::RM_LEVEL_LIST;

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

        // Karyawan yang sudah pensiun: data jabatan dan masa pensiun dibekukan sebagai arsip.
        if ($employee->isSeparated()) {
            $validated = array_diff_key($validated, array_flip(['status_penugasan', 'golongan', 'tanggal_dalam_jabatan', 'tmt_unit_kerja', 'job_grade', 'person_grade', 'tanggal_mbt', 'tanggal_pensiun']));
        }

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
        if (! $employee->isSeparated()) {
            $validated['status_penugasan'] = $validated['status_penugasan'] ?? 'Definitif';
        }

        DB::transaction(function () use ($employee, $validated) {
            $employee->update($validated);
        });

        return redirect()->route('admin.employees.index')->with('success', 'Data karyawan berhasil diperbarui.');
    }

    /**
     * Memberhentikan karyawan dari jabatan (pensiun dini, mengundurkan diri, meninggal dunia, PHK, atau pensiun yang
     * dimajukan). Pensiun normal berjalan otomatis; tindakan ini wajib bertanggal dan tercatat pada Riwayat Perubahan.
     */
    public function separate(Request $request, $nik, PensiunService $pensiun)
    {
        Gate::authorize('separate-employee');

        $validated = $request->validate([
            'alasan' => ['required', Rule::in(Employee::ALASAN_BERHENTI)],
            'tanggal_berhenti' => ['required', 'date'],
            'nomor_sk' => ['nullable', 'string', 'max:100'],
            'tanggal_sk' => ['nullable', 'date', 'required_with:nomor_sk'],
        ], [
            'alasan.required' => 'Alasan pemberhentian wajib dipilih.',
            'tanggal_berhenti.required' => 'Tanggal berhenti wajib diisi.',
            'tanggal_sk.required_with' => 'Tanggal SK wajib diisi bila nomor SK diisi.',
        ]);

        $employee = Employee::findOrFail($nik);

        $done = $pensiun->separate(
            $employee,
            $validated['alasan'],
            Carbon::parse($validated['tanggal_berhenti']),
            $validated['nomor_sk'] ?? null,
            isset($validated['tanggal_sk']) ? Carbon::parse($validated['tanggal_sk']) : null,
            $request->user(),
        );

        return redirect()->route('admin.employees.show', $employee)->with(
            $done ? 'success' : 'error',
            $done ? 'Karyawan berhasil diberhentikan dari jabatan dan kursi formasinya dikosongkan.' : 'Karyawan sudah diberhentikan sebelumnya.'
        );
    }

    /** Mencatat SK pemberhentian yang terbit setelah pemberhentian diproses. */
    public function recordSeparationSk(Request $request, $nik, PensiunService $pensiun)
    {
        Gate::authorize('separate-employee');

        $validated = $request->validate([
            'nomor_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date'],
        ], [
            'nomor_sk.required' => 'Nomor SK wajib diisi.',
            'tanggal_sk.required' => 'Tanggal SK wajib diisi.',
        ]);

        $employee = Employee::findOrFail($nik);
        abort_unless($employee->isSeparated(), 422, 'Karyawan belum diberhentikan.');

        $pensiun->catatSk($employee, $validated['nomor_sk'], Carbon::parse($validated['tanggal_sk']), $request->user());

        return redirect()->route('admin.employees.show', $employee)->with('success', 'SK pemberhentian berhasil dicatat.');
    }

    // Delete employee
    public function destroy(Request $request, $nik, EmployeeDeletionService $deletion)
    {
        Gate::authorize('delete-employee');

        $validated = $request->validate(['reason' => EmployeeDeletionService::REASON_RULES]);

        $employee = Employee::findOrFail($nik);

        // Archive (master data, placement history, actor, reason), delete, and remove the photo after commit.
        $deletion->deleteMany(Employee::whereKey($employee->nik), $request->user(), $validated['reason']);

        return redirect()->route('admin.employees.index')->with('success', 'Data karyawan berhasil dihapus.');
    }

    /**
     * Hapus massal data karyawan terpilih secara transaksional.
     */
    public function bulkDestroy(Request $request, EmployeeDeletionService $deletion)
    {
        // Same ability as the Daftar Karyawan bulk action.
        Gate::authorize('delete-employee');

        $validated = $request->validate([
            'niks' => 'required|array|min:1',
            'niks.*' => 'required|string|size:8',
            'reason' => EmployeeDeletionService::REASON_RULES,
        ]);

        // Same archive, photo-and-row deletion (one transaction) as the Daftar Karyawan bulk action.
        $count = $deletion->deleteMany(Employee::whereIn('nik', $validated['niks']), $request->user(), $validated['reason']);

        return redirect()->route('admin.employees.index')->with('success', "{$count} data karyawan terpilih berhasil dihapus.");
    }
}
