<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Exports\EmployeesExport;
use App\Imports\EmployeesImport;
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
                $q->where('nik', 'like', '%' . $request->search . '%')
                    ->orWhere('nama', 'like', '%' . $request->search . '%');
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

        if ($request->filled('golongan_2024')) {
            $query->where('golongan_2024', $request->golongan_2024);
        }

        $employees = $query->paginate(20);

        // Get unique values for filters
        $jabatans = Employee::select('jabatan')->distinct()->pluck('jabatan');
        $levels = Employee::select('level')->distinct()->pluck('level');
        $units = Employee::select('unit_kerja')->distinct()->pluck('unit_kerja');
        $golongans = Employee::select('golongan_2024')->distinct()->pluck('golongan_2024');

        return view('admin.employees.index', compact('employees', 'jabatans', 'levels', 'units', 'golongans'));
    }

    // Store new employee
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nik' => 'required|string|max:20|unique:employees,nik',
            'nama' => 'required|string|max:255',
            'jabatan' => 'required|string|max:255',
            'level' => 'required|string|max:255',
            'unit_kerja' => 'required|string|max:255',
            'golongan_2024' => 'nullable|string|max:50',
            'tanggal_dalam_jabatan' => 'nullable|date',
            'tmt_unit_kerja' => 'nullable|date',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'tmt_bekerja' => 'nullable|date',
            'tanggal_diangkat_staf' => 'nullable|date',
            'susunan_keluarga' => 'nullable|string|max:255',
            'job_grader' => 'nullable|string|max:100',
            'person_grade' => 'nullable|string|max:100',
            'tanggal_mbt' => 'nullable|date',
            'tanggal_pensiun' => 'nullable|date',
            'agama' => 'nullable|string|max:50',
            'pendidikan_terakhir' => 'nullable|string|max:100',
            'sekolah' => 'nullable|string|max:255',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('photos', 'public');
        }

        Employee::create($validated);

        return redirect()->route('admin.employees.index')->with('success', 'Employee added successfully.');
    }

    public function show($nik)
    {
        $employee = Employee::where('nik', $nik)->firstOrFail();
        return view('admin.employees.show', compact('employee'));
    }
 
    public function import(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:2048',
        ]);

        $import = new EmployeesImport;
        Excel::import($import, $request->file('import_file'));

        return redirect()->route('admin.employees.index')
            ->with([
                'success' => "{$import->created} data baru ditambahkan, {$import->updated} data diperbarui, {$import->skipped} baris dilewati karena error.",
                'import_errors' => $import->errors,
            ]);
    }

    public function export(Request $request)
    {
        $filters = $request->only([
            'search',
            'jabatan',
            'level',
            'unit_kerja',
            'golongan_2024',
        ]);

        return Excel::download(new EmployeesExport($filters), 'data_karyawan.csv');
    }

    public function downloadTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employee_import_template.csv"',
        ];

        $columns = [
            'nik',
            'nama',
            'jabatan',
            'level',
            'unit_kerja',
            'golongan_2024',
            'tanggal_dalam_jabatan',
            'tmt_unit_kerja',
            'tempat_lahir',
            'tanggal_lahir',
            'tmt_bekerja',
            'tanggal_diangkat_staf',
            'susunan_keluarga',
            'job_grader',
            'person_grade',
            'tanggal_mbt',
            'tanggal_pensiun',
            'agama',
            'pendidikan_terakhir',
            'sekolah'
        ];

        $callback = function () use ($columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            // Optional example row (you can remove this if you want a blank file)
            fputcsv($handle, [
                '1234567890',
                'John Doe',
                'Staff',
                'IIIa',
                'IT Dept',
                'A1',
                '2022-01-01',
                1,
                6,
                '2021-06-01',
                2,
                3,
                30,
                5,
                'Jakarta',
                '1995-05-05',
                '2020-01-01',
                '2020-12-01',
                'Menikah',
                'JG1',
                'PG2',
                '2023-08-01',
                '2055-05-01',
                'Islam',
                'S1 Teknik',
                'Universitas Indonesia'
            ]);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function create()
    {
        return view('admin.employees.add-employee');
    }

    // Edit existing employee
    public function edit($nik)
    {
        $employee = Employee::findOrFail($nik);
        return view('admin.employees.edit-employee', compact('employee'));
    }

    // Update existing employee
    public function update(Request $request, $nik)
    {
        $employee = Employee::findOrFail($nik);

        $validated = $request->validate([
            'nama' => 'required',
            'jabatan' => 'required',
            'level' => 'required',
            'unit_kerja' => 'required',
            'golongan_2024' => 'nullable',
            'tanggal_dalam_jabatan' => 'nullable|date',
            'tmt_unit_kerja' => 'nullable|date',
            'tempat_lahir' => 'nullable',
            'tanggal_lahir' => 'nullable|date',
            'tmt_bekerja' => 'nullable|date',
            'tanggal_diangkat_staf' => 'nullable|date',
            'susunan_keluarga' => 'nullable',
            'job_grader' => 'nullable',
            'person_grade' => 'nullable',
            'tanggal_mbt' => 'nullable|date',
            'tanggal_pensiun' => 'nullable|date',
            'agama' => 'nullable',
            'pendidikan_terakhir' => 'nullable',
            'sekolah' => 'nullable',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
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

        $employee->update($validated);

        return redirect()->route('admin.employees.index')->with('success', 'Employee updated successfully.');
    }

    // Delete employee
    public function destroy($nik)
    {
        $employee = Employee::findOrFail($nik);

        // Delete foto if exists
        if ($employee->foto && Storage::disk('public')->exists($employee->foto)) {
            Storage::disk('public')->delete($employee->foto);
        }

        $employee->delete();

        return redirect()->route('admin.employees.index')->with('success', 'Employee deleted successfully.');
    }
}
