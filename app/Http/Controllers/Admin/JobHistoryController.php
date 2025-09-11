<?php

namespace App\Http\Controllers\Admin;

use App\Models\JobHistory;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class JobHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Employee $employee)
    {
        $histories = $employee->jobHistories()->paginate(10);
        return view('admin.employees.job-history.index', compact('employee', 'histories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Employee $employee)
    {
        $mutasiOptions = JobHistory::MUTASI_TYPES;
        return view('admin.employees.job-history.create', compact('employee', 'mutasiOptions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'jabatan'      => 'required|string|max:255',
            'unit_kerja'   => 'required|string|max:255',
            'level'        => 'nullable|string|max:255',
            'golongan'     => 'nullable|string|max:50',
            'tmt_awal'     => 'required|date',
            'tmt_akhir'    => 'nullable|date|after_or_equal:tmt_awal',
            'jenis_mutasi' => 'nullable|string|in:' . implode(',', JobHistory::MUTASI_TYPES),
            'nomor_sk'     => 'nullable|string|max:100',
            'tanggal_sk'   => 'nullable|date',
            'catatan'      => 'nullable|string|max:2000',
        ]);

        $data['employee_nik'] = $employee->nik;

        // Optional rule: only one active row — close existing active one
        if (empty($data['tmt_akhir'])) {
            JobHistory::where('employee_nik', $employee->nik)
                ->whereNull('tmt_akhir')
                ->update(['tmt_akhir' => now()->subDay()->toDateString()]);
        }

        JobHistory::create($data);

        // Optional sync: update employee “current” fields when active row is added
        if (empty($data['tmt_akhir'])) {
            $employee->update([
                'jabatan'               => $data['jabatan'],
                'unit_kerja'            => $data['unit_kerja'],
                'level'                 => $data['level'] ?? $employee->level,
                'golongan'              => $data['golongan'] ?? $employee->golongan,
                'tanggal_dalam_jabatan' => $data['tmt_awal'],
                'tmt_unit_kerja'        => $data['tmt_awal'],
            ]);
        }

        return redirect()
            ->to(route('admin.employees.show', $employee->nik) . '#riwayat-jabatan')
            ->with('success', 'Riwayat jabatan ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee, JobHistory $job_history)
    {
        return view('admin.employees.job-history.show', compact('employee', 'job_history'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee, JobHistory $job_history)
    {
        $mutasiOptions = JobHistory::MUTASI_TYPES;
        return view('admin.employees.job-history.edit', [
            'employee'     => $employee,
            'jobHistory'   => $job_history,       
            'mutasiOptions' => $mutasiOptions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee, JobHistory $job_history)
    {
        $data = $request->validate([
            'jabatan'      => 'required|string|max:255',
            'unit_kerja'   => 'required|string|max:255',
            'level'        => 'nullable|string|max:255',
            'golongan'     => 'nullable|string|max:50',
            'tmt_awal'     => 'required|date',
            'tmt_akhir'    => 'nullable|date|after_or_equal:tmt_awal',
            'jenis_mutasi' => 'nullable|string|in:' . implode(',', JobHistory::MUTASI_TYPES),
            'nomor_sk'     => 'nullable|string|max:100',
            'tanggal_sk'   => 'nullable|date',
            'catatan'      => 'nullable|string|max:2000',
        ]);

        $job_history->update($data);

        // Optional sync if this row is active
        if (empty($data['tmt_akhir'])) {
            $employee->update([
                'jabatan'               => $data['jabatan'],
                'unit_kerja'            => $data['unit_kerja'],
                'level'                 => $data['level'] ?? $employee->level,
                'golongan'              => $data['golongan'] ?? $employee->golongan,
                'tanggal_dalam_jabatan' => $data['tmt_awal'],
                'tmt_unit_kerja'        => $data['tmt_awal'],
            ]);
        }

        return back()->with('success', 'Riwayat jabatan diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee, JobHistory $job_history)
    {
        $job_history->delete();
        return redirect()
            ->to(route('admin.employees.show', $employee->nik) . '#riwayat-jabatan')
            ->with('success', 'Riwayat jabatan dihapus.');
    }
}
