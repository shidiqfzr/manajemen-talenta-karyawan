<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Services\TrainingService;
use Illuminate\Http\Request;

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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'jenis' => 'required|string|max:255',
            'metode' => 'required|string|max:255',
            'tanggal_mulai' => 'required|date',
            'tanggal_akhir' => 'required|date',
            'biaya_tiket_peserta' => 'required|integer|min:0',
            'biaya_pelatihan' => 'required|integer|min:0',
            'penyelenggara' => 'required|string|max:255',
            'nomor_surat' => 'required|string|max:255',
            'tanggal_surat' => 'required|date',
            'keterangan' => 'nullable|string',
            'bidang_pelatihan' => 'required|string|max:255',
            'jam_belajar_per_hari' => 'required|integer|min:1',
        ]);

        $training = Training::create($validated);
        $this->trainingService->recalculateManHours($training);

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

    public function update(Request $request, Training $training)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'jenis' => 'required|string|max:255',
            'metode' => 'required|string|max:255',
            'tanggal_mulai' => 'required|date',
            'tanggal_akhir' => 'required|date',
            'biaya_tiket_peserta' => 'required|integer|min:0',
            'biaya_pelatihan' => 'required|integer|min:0',
            'penyelenggara' => 'required|string|max:255',
            'nomor_surat' => 'required|string|max:255',
            'tanggal_surat' => 'required|date',
            'keterangan' => 'nullable|string',
            'bidang_pelatihan' => 'required|string|max:255',
            'jam_belajar_per_hari' => 'required|integer|min:1',
        ]);

        $training->update($validated);
        $this->trainingService->recalculateManHours($training);

        return redirect()->route('admin.trainings.show', $training->id)->with('success', 'Data pelatihan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $training = Training::findOrFail($id);
        $training->delete();

        return redirect()->route('admin.trainings.index')->with('success', 'Pelatihan berhasil dihapus.');
    }
}
