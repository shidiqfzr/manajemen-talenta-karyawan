<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeletionReasonRequest;
use App\Http\Requests\Admin\EvaluationRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Services\EvaluationService;

class EvaluationController extends Controller
{
    protected EvaluationService $evaluationService;

    public function __construct(EvaluationService $evaluationService)
    {
        $this->evaluationService = $evaluationService;
    }

    /**
     * Display the evaluations list (queried by the EvaluationIndexTable Livewire component).
     */
    public function index()
    {
        return view('admin.evaluations.index');
    }

    /**
     * Show the form for creating a new evaluation.
     * If $employee_nik is provided, pre-fill employee.
     */
    public function create(?string $employee_nik = null)
    {
        if ($employee_nik) {
            $employee = Employee::where('nik', $employee_nik)->firstOrFail();

            return view('admin.evaluations.add-evaluation', compact('employee'));
        }

        $employees = Employee::aktif()->orderBy('nama')->get(['nik', 'nama']);

        return view('admin.evaluations.add-evaluation', compact('employees'));
    }

    /**
     * Store a newly created evaluation in storage.
     */
    public function store(EvaluationRequest $request)
    {
        $this->evaluationService->processStore($request->validated());

        return redirect()->route('admin.evaluations.index')->with('success', 'Penilaian berhasil disimpan.');
    }

    /**
     * Display the specified evaluation.
     */
    public function show(Evaluation $evaluation)
    {
        // Use the relation to fetch employee (relation uses employee_nik)
        $employee = $evaluation->employee;

        return view('admin.evaluations.show', compact('evaluation', 'employee'));
    }

    /**
     * Show the form for editing the specified evaluation.
     */
    public function edit(Evaluation $evaluation)
    {
        $employee = $evaluation->employee;

        return view('admin.evaluations.edit-evaluation', compact('evaluation', 'employee'));
    }

    /**
     * Update the specified evaluation in storage.
     */
    public function update(EvaluationRequest $request, Evaluation $evaluation)
    {
        $this->evaluationService->processUpdate($evaluation, $request->validated());

        return redirect()->route('admin.evaluations.index')->with('success', 'Penilaian berhasil diperbarui.');
    }

    /**
     * Remove the specified evaluation from storage.
     */
    public function destroy(DeletionReasonRequest $request, Evaluation $evaluation)
    {
        $this->evaluationService->processDestroy($evaluation, $request->validated('alasan'));

        return redirect()->route('admin.evaluations.index')->with('success', 'Penilaian berhasil dihapus.');
    }
}
