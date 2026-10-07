<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EmployeesExport;
use App\Exports\EmployeeTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\EmployeesImport;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    // Display all employees
    public function index(Request $request)
    {
        $query = Employee::query();

        // Text search
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nik', 'like', '%'.$request->search.'%')
                    ->orWhere('nama', 'like', '%'.$request->search.'%');
            });
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

        if ($request->filled('golongan')) {
            $query->where('golongan', $request->golongan);
        }

        // Period filter: Tahun Masuk (TMT Bekerja)
        if ($request->filled('tahun_masuk')) {
            $query->whereYear('tmt_bekerja', $request->tahun_masuk);
        }

        // Period filter: Bulan Masuk (TMT Bekerja)
        if ($request->filled('bulan_masuk')) {
            $query->whereMonth('tmt_bekerja', $request->bulan_masuk);
        }

        // Reactive Summary KPI Metrics based on active filters
        $filteredQuery = clone $query;
        $totalEmployees = $filteredQuery->count();
        $totalUnits = (clone $query)->whereNotNull('unit_kerja')->where('unit_kerja', '!=', '')->distinct()->count('unit_kerja');
        $karpimCount = (clone $query)->where(function ($q) {
            $q->where('level', 'like', '%Karpim%')
                ->orWhere('level', 'like', '%Pimpinan%');
        })->count();
        $pelaksanaCount = max(0, $totalEmployees - $karpimCount);

        // Global count for overall reference
        $globalTotalEmployees = Employee::count();

        $employees = $query->orderBy('nama', 'asc')->paginate(20)->withQueryString();

        // Get unique sorted values for filters
        $jabatans = Employee::select('jabatan')->whereNotNull('jabatan')->where('jabatan', '!=', '')->distinct()->orderBy('jabatan')->pluck('jabatan');
        $levels = Employee::select('level')->whereNotNull('level')->where('level', '!=', '')->distinct()->orderBy('level')->pluck('level');
        $units = Employee::select('unit_kerja')->whereNotNull('unit_kerja')->where('unit_kerja', '!=', '')->distinct()->orderBy('unit_kerja')->pluck('unit_kerja');
        $golongans = Employee::select('golongan')->whereNotNull('golongan')->where('golongan', '!=', '')->distinct()->orderBy('golongan')->pluck('golongan');

        // Dynamic hire years from database
        $availableYears = Employee::whereNotNull('tmt_bekerja')
            ->pluck('tmt_bekerja')
            ->map(function ($date) {
                try {
                    return Carbon::parse($date)->year;
                } catch (\Exception $e) {
                    return null;
                }
            })
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

        return view('admin.employees.index', compact(
            'employees',
            'jabatans',
            'levels',
            'units',
            'golongans',
            'availableYears',
            'months',
            'totalEmployees',
            'totalUnits',
            'karpimCount',
            'pelaksanaCount',
            'globalTotalEmployees'
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'regex:/^[0-9]{8}$/', 'unique:employees,nik'],
            'nama' => 'required|string|max:255',
            'jabatan' => 'required|string|max:255',
            'level' => 'required|string|max:255',
            'rm_level' => 'nullable|string|in:RM-1,RM-2,RM-3,RM-4',
            'unit_kerja' => 'required|string|max:255',
            'golongan' => 'nullable|string|max:50',
            'tanggal_dalam_jabatan' => 'nullable|date',
            'tmt_unit_kerja' => 'nullable|date',
            'tempat_lahir' => 'nullable|string|max:100',
            'jenis_kelamin' => 'nullable|string|in:L,P,Laki-laki,Perempuan',
            'tanggal_lahir' => 'nullable|date',
            'tmt_bekerja' => 'nullable|date',
            'tanggal_diangkat_staf' => 'nullable|date',
            'susunan_keluarga' => 'nullable|string|max:255',
            'job_grade' => 'nullable|integer|between:1,16',
            'person_grade' => 'nullable|integer|between:1,16',
            'jalur_masuk' => 'nullable|string|in:CKP,Talent Scouting,RBB,Reguler',
            'tanggal_mbt' => 'nullable|date',
            'tanggal_pensiun' => 'nullable|date',
            'agama' => 'nullable|string|max:50',
            'pendidikan_terakhir' => 'nullable|string|max:100',
            'sekolah' => 'nullable|string|max:255',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'nik.regex' => 'Format NIK harus berupa 8 digit angka numerik (contoh: 13004521).',
            'nik.unique' => 'NIK ini sudah terdaftar dalam sistem.',
            'job_grade.between' => 'Job Grade harus berada dalam rentang 1 sampai 16.',
            'person_grade.between' => 'Person Grade harus berada dalam rentang 1 sampai 16.',
        ]);

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('photos', 'public');
        }

        // Standardize data (Uppercase nama & trim unit_kerja)
        $validated['nama'] = strtoupper(trim($validated['nama']));
        $validated['unit_kerja'] = trim($validated['unit_kerja']);
        $validated['jalur_masuk'] = $validated['jalur_masuk'] ?? 'Reguler';

        // Auto-calculate Pensiun (56 tahun) & MBT (55 tahun) if tanggal_lahir is present (DOMAIN.md Bab 5.1)
        if (! empty($validated['tanggal_lahir'])) {
            if (empty($validated['tanggal_pensiun'])) {
                $validated['tanggal_pensiun'] = Employee::calculateTanggalPensiun($validated['tanggal_lahir']);
            }
            if (empty($validated['tanggal_mbt']) && ! empty($validated['tanggal_pensiun'])) {
                $validated['tanggal_mbt'] = Employee::calculateTanggalMbt($validated['tanggal_pensiun']);
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

    public function import(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:xlsx,xls|max:2048',
        ]);

        $import = new EmployeesImport;
        Excel::import($import, $request->file('import_file'));

        $summary = $import->getImportSummary();

        return redirect()
            ->route('admin.employees.index')
            ->with([
                'success' => "{$summary['created']} data baru ditambahkan, {$summary['updated']} data diperbarui, {$summary['skipped']} rows skipped.",
                'import_summary' => $summary,
            ]);
    }

    public function export(Request $request)
    {
        $filters = $request->only([
            'search',
            'jabatan',
            'level',
            'unit_kerja',
            'golongan',
            'tahun_masuk',
            'bulan_masuk',
        ]);

        return Excel::download(new EmployeesExport($filters), 'data_karyawan.xlsx');
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
    public function update(Request $request, $nik)
    {
        $employee = Employee::findOrFail($nik);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'jabatan' => 'required|string|max:255',
            'level' => 'required|string|max:255',
            'rm_level' => 'nullable|string|in:RM-1,RM-2,RM-3,RM-4',
            'unit_kerja' => 'required|string|max:255',
            'golongan' => 'nullable|string|max:50',
            'tanggal_dalam_jabatan' => 'nullable|date',
            'tmt_unit_kerja' => 'nullable|date',
            'tempat_lahir' => 'nullable|string|max:100',
            'jenis_kelamin' => 'nullable|string|in:L,P,Laki-laki,Perempuan',
            'tanggal_lahir' => 'nullable|date',
            'tmt_bekerja' => 'nullable|date',
            'tanggal_diangkat_staf' => 'nullable|date',
            'susunan_keluarga' => 'nullable|string|max:255',
            'job_grade' => 'nullable|integer|between:1,16',
            'person_grade' => 'nullable|integer|between:1,16',
            'jalur_masuk' => 'nullable|string|in:CKP,Talent Scouting,RBB,Reguler',
            'tanggal_mbt' => 'nullable|date',
            'tanggal_pensiun' => 'nullable|date',
            'agama' => 'nullable|string|max:50',
            'pendidikan_terakhir' => 'nullable|string|max:100',
            'sekolah' => 'nullable|string|max:255',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'job_grade.between' => 'Job Grade harus berada dalam rentang 1 sampai 16.',
            'person_grade.between' => 'Person Grade harus berada dalam rentang 1 sampai 16.',
        ]);

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
        $validated['unit_kerja'] = trim($validated['unit_kerja']);

        // Auto-calculate Pensiun & MBT if tanggal_lahir is provided and pensiun/mbt are empty
        if (! empty($validated['tanggal_lahir'])) {
            if (empty($validated['tanggal_pensiun'])) {
                $validated['tanggal_pensiun'] = Employee::calculateTanggalPensiun($validated['tanggal_lahir']);
            }
            if (empty($validated['tanggal_mbt']) && ! empty($validated['tanggal_pensiun'])) {
                $validated['tanggal_mbt'] = Employee::calculateTanggalMbt($validated['tanggal_pensiun']);
            }
        }

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
}
