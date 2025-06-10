<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\Employee;
use App\Models\EmployeeStatistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TrainingController extends Controller
{
    /**
     * Hitung jumlah man hours berdasarkan peserta & lama pelatihan
     */
    protected function calculateManHours(Training $training)
    {
        $jumlahPeserta = $training->employees()->count();
        $jumlahHari = Carbon::parse($training->tanggal_mulai)->diffInDays(Carbon::parse($training->tanggal_akhir)) + 1;
        $jamPerHari = $training->jam_belajar_per_hari;

        $training->jumlah_man_hours = $jumlahHari * $jamPerHari * $jumlahPeserta;
        $training->save();
    }

    // Display training
    public function index(Request $request)
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $trainings = Training::withCount('employees')
            ->when($start && $end, function ($query) use ($start, $end) {
                $query->whereBetween('tanggal_mulai', [$start, $end]);
            })
            ->get();

        $totalManHours = $trainings->sum('jumlah_man_hours');
        $totalTiketPeserta = $trainings->sum('biaya_tiket_peserta');
        $totalBiayaPelatihan = $trainings->sum('biaya_pelatihan');

        $totalKaryawan = null;
        if ($start && $end) {
            $startDate = Carbon::parse($start)->startOfMonth();
            $endDate = Carbon::parse($end)->endOfMonth();
            $statistics = EmployeeStatistic::whereBetween('periode', [$startDate, $endDate])->get();
            if ($statistics->count()) {
                $totalKaryawan = $statistics->sum('total_karyawan');
            }
        }

        return view('admin.trainings.index', compact(
            'trainings',
            'totalManHours',
            'totalTiketPeserta',
            'totalBiayaPelatihan',
            'totalKaryawan'
        ));
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
            'tiket_peserta' => 'required|integer|min:0',
            'biaya_pelatihan' => 'required|integer|min:0',
            'penyelenggara' => 'required|string|max:255',
            'nomor_surat' => 'required|string|max:255',
            'tanggal_surat' => 'required|date',
            'keterangan' => 'nullable|string',
            'bidang_pelatihan' => 'required|string|max:255',
            'jam_belajar_per_hari' => 'required|integer|min:1',
        ]);

        $training = Training::create($validated);
        $this->calculateManHours($training);

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
        $training->load('employees');
        $allEmployees = \App\Models\Employee::all();

        return view('admin.trainings.show', compact('training', 'allEmployees'));
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
            'tiket_peserta' => 'required|integer|min:0',
            'biaya_pelatihan' => 'required|integer|min:0',
            'penyelenggara' => 'required|string|max:255',
            'nomor_surat' => 'required|string|max:255',
            'tanggal_surat' => 'required|date',
            'keterangan' => 'nullable|string',
            'bidang_pelatihan' => 'required|string|max:255',
            'jam_belajar_per_hari' => 'required|integer|min:1',
        ]);

        $training->update($validated);
        $this->calculateManHours($training);

        return redirect()->route('admin.trainings.show', $training->id)->with('success', 'Data pelatihan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $training = Training::findOrFail($id);
        $training->delete();

        return redirect()->route('admin.trainings.index')->with('success', 'Pelatihan berhasil dihapus.');
    }
}
