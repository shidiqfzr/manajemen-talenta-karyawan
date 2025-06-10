<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TrainingParticipantController extends Controller
{
    /**
     * Show the form for creating a new participant.
     */
    public function create(Training $training)
    {
        $employees = Employee::all();
        return view('admin.trainings.add-participant', compact('training', 'employees'));
    }

    /**
     * Store a newly added participant in storage.
     */
    public function store(Request $request, Training $training)
    {
        $request->validate([
            'employee_nik' => 'required|exists:employees,nik',
            'sertifikat' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $sertifikatPath = null;

        // Simpan sertifikat jika diunggah
        if ($request->hasFile('sertifikat')) {
            $sertifikatPath = $request->file('sertifikat')->store('sertifikat', 'public');
        }

        // Simpan ke pivot table
        $training->employees()->syncWithoutDetaching([
            $request->employee_nik => [
                'sertifikat' => $sertifikatPath,
            ],
        ]);

        return redirect()->route('admin.trainings.show', $training->id)
            ->with('success', 'Peserta berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified participant.
     */
    public function edit(Training $training, Employee $employee)
    {
        $pivotData = $training->employees()->where('employee_nik', $employee->nik)->firstOrFail()->pivot;

        return view('admin.trainings.participants.edit', compact('training', 'employee', 'pivotData'));
    }

    /**
     * Update the specified participant in storage.
     */
    public function update(Request $request, Training $training, Employee $employee)
    {
        $request->validate([
            'jam_belajar_per_hari' => 'required|integer|min:1',
            'keterangan' => 'nullable|string',
        ]);

        $jamBelajar = $request->jam_belajar_per_hari;

        $training->employees()->updateExistingPivot($employee->nik, [
            'jam_belajar_per_hari' => $jamBelajar,
            'keterangan' => $request->keterangan,
        ]);

        return redirect()->route('admin.trainings.show', $training->id)
            ->with('success', 'Data peserta berhasil diperbarui.');
    }

    /**
     * Remove the specified participant from the training.
     */
    public function destroy(Training $training, Employee $employee)
    {
        $training->employees()->detach($employee->nik);

        return back()->with('success', 'Peserta berhasil dihapus.');
    }
}
