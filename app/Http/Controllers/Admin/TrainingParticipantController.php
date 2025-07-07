<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\Employee;
use App\Services\TrainingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TrainingParticipantController extends Controller
{
    protected $trainingService;

    public function __construct(TrainingService $trainingService)
    {
        $this->trainingService = $trainingService;
    }

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
            'employee_niks' => 'required|array|min:1',
            'employee_niks.*' => 'exists:employees,nik',
            'sertifikat' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $sertifikatPath = null;

        // Simpan sertifikat jika diunggah
        if ($request->hasFile('sertifikat')) {
            $sertifikatPath = $request->file('sertifikat')->store('sertifikat', 'public');
        }

        // Simpan ke pivot table
        foreach ($request->employee_niks as $nik) {
            $training->employees()->syncWithoutDetaching([
                $nik => [
                    'sertifikat' => $sertifikatPath,
                ],
            ]);
        }

        $this->trainingService->recalculateManHours($training);

        return redirect()->route('admin.trainings.show', $training->id)
            ->with('success', 'Peserta berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified participant.
     */
    public function edit(Training $training, Employee $employee)
    {
        $pivotData = $training->employees()->where('employee_nik', $employee->nik)->firstOrFail()->pivot;

        return view('admin.trainings.edit-participant', compact('training', 'employee', 'pivotData'));
    }

    /**
     * Update the specified participant in storage.
     */
    public function update(Request $request, Training $training, Employee $employee)
    {
        $request->validate([
            'sertifikat' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $sertifikatPath = null;

        // Simpan file jika ada
        if ($request->hasFile('sertifikat')) {
            $sertifikatPath = $request->file('sertifikat')->store('sertifikat', 'public');

            // Hapus sertifikat lama jika ada
            $existing = $training->employees()->where('employee_nik', $employee->nik)->first();
            if ($existing && $existing->pivot->sertifikat) {
                Storage::disk('public')->delete($existing->pivot->sertifikat);
            }

            // Update sertifikat pada pivot table
            $training->employees()->updateExistingPivot($employee->nik, [
                'sertifikat' => $sertifikatPath,
            ]);
        }

        return redirect()->route('admin.trainings.show', $training->id)
            ->with('success', 'Sertifikat peserta berhasil diperbarui.');
    }

    /**
     * Remove the specified participant from the training.
     */
    public function destroy(Training $training, Employee $employee)
    {
        $training->employees()->detach($employee->nik);

        $this->trainingService->recalculateManHours($training);

        return back()->with('success', 'Peserta berhasil dihapus.');
    }
}
