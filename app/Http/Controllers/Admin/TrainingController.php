<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeletionReasonRequest;
use App\Http\Requests\Admin\TrainingRequest;
use App\Models\Training;
use App\Services\TrainingService;

class TrainingController extends Controller
{
    protected $trainingService;

    public function __construct(TrainingService $trainingService)
    {
        $this->trainingService = $trainingService;
    }

    // Display training
    /**
     * Manajemen Pelatihan page; the list, its filters (including the legacy start_date / end_date URL keys)
     * and the summary cards are handled by the TrainingIndexTable Livewire component.
     */
    public function index()
    {
        return view('admin.trainings.index');
    }

    // Store new training
    public function store(TrainingRequest $request)
    {
        $this->trainingService->create($request->validated());

        return redirect()->route('admin.trainings.index')->with('success', 'Pelatihan berhasil ditambahkan');
    }

    public function create()
    {
        return view('admin.trainings.add-training');
    }

    /**
     * Display the training details and its participants.
     */
    public function show(Training $training)
    {
        return view('admin.trainings.show', compact('training'));
    }

    public function edit(Training $training)
    {
        return view('admin.trainings.edit-training', compact('training'));
    }

    public function update(TrainingRequest $request, Training $training)
    {
        $this->trainingService->update($training, $request->validated());

        return redirect()->route('admin.trainings.show', $training->id)->with('success', 'Data pelatihan berhasil diperbarui.');
    }

    public function destroy(DeletionReasonRequest $request, Training $training)
    {
        $this->trainingService->delete($training, $request->validated('alasan'));

        return redirect()->route('admin.trainings.index')->with('success', 'Pelatihan berhasil dihapus.');
    }
}
