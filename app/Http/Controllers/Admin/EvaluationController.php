<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Employee;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    /**
     * Display a paginated listing of evaluations.
     */
    public function index()
    {
        $evaluations = Evaluation::with('employee')->latest()->paginate(20);

        return view('admin.evaluations.index', compact('evaluations'));
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

        $employees = Employee::all();
        return view('admin.evaluations.add-evaluation', compact('employees'));
    }

    /**
     * Store a newly created evaluation in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_nik' => ['required', 'exists:employees,nik'],
            'nilai_kepemimpinan' => 'nullable|numeric',
            'nilai_perilaku_budaya' => 'nullable|numeric',
            'nilai_pengalaman_teknis' => 'nullable|numeric',
            'nilai_kematangan_pribadi' => 'nullable|numeric',
            'skor_smkbk_9box' => 'nullable|numeric',
            'skor_cli_9box' => 'nullable|numeric',
            'kategori_9box' => 'nullable|string',
            'bidang_tugas' => 'nullable|string',
            'lembaga_asesmen' => 'nullable|string',
            'tanggal_pelaksanaan_asesmen' => 'nullable|date',
            'hasil_skor_asesmen' => 'nullable|numeric',
            'kategori_asesmen' => 'nullable|string',
            'keterangan_asesmen' => 'nullable|string',
            'expired_asesmen' => 'nullable|date',
            'keterangan' => 'nullable|string',
        ]);

        // Ensure numeric values are floats and default to 0 when null
        $nk = (float) ($data['nilai_kepemimpinan'] ?? 0.0);
        $npb = (float) ($data['nilai_perilaku_budaya'] ?? 0.0);
        $npt = (float) ($data['nilai_pengalaman_teknis'] ?? 0.0);
        $nkp = (float) ($data['nilai_kematangan_pribadi'] ?? 0.0);

        $data['nilai_tertimbang'] = ($nk * 0.4) + ($npb * 0.3) + ($npt * 0.2) + ($nkp * 0.1);

        Evaluation::create($data);

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
    public function update(Request $request, Evaluation $evaluation)
    {
        $data = $request->validate([
            'nilai_kepemimpinan' => 'nullable|numeric',
            'nilai_perilaku_budaya' => 'nullable|numeric',
            'nilai_pengalaman_teknis' => 'nullable|numeric',
            'nilai_kematangan_pribadi' => 'nullable|numeric',
            'skor_smkbk_9box' => 'nullable|numeric',
            'skor_cli_9box' => 'nullable|numeric',
            'kategori_9box' => 'nullable|string',
            'bidang_tugas' => 'nullable|string',
            'tanggal_diangkat_staf' => 'nullable|date',
            'masa_kerja_tahun' => 'nullable|integer',
            'masa_kerja_bulan' => 'nullable|integer',
            'lembaga_asesmen' => 'nullable|string',
            'tanggal_pelaksanaan_asesmen' => 'nullable|date',
            'hasil_skor_asesmen' => 'nullable|numeric',
            'kategori_asesmen' => 'nullable|string',
            'keterangan_asesmen' => 'nullable|string',
            'expired_asesmen' => 'nullable|date',
            'keterangan' => 'nullable|string',
        ]);

        $nk = (float) ($data['nilai_kepemimpinan'] ?? 0.0);
        $npb = (float) ($data['nilai_perilaku_budaya'] ?? 0.0);
        $npt = (float) ($data['nilai_pengalaman_teknis'] ?? 0.0);
        $nkp = (float) ($data['nilai_kematangan_pribadi'] ?? 0.0);

        $data['nilai_tertimbang'] = ($nk * 0.4) + ($npb * 0.3) + ($npt * 0.2) + ($nkp * 0.1);

        $evaluation->update($data);

        return redirect()->route('admin.evaluations.index')->with('success', 'Penilaian berhasil diperbarui.');
    }

    /**
     * Remove the specified evaluation from storage.
     */
    public function destroy(Evaluation $evaluation)
    {
        $evaluation->delete();

        return redirect()->route('admin.evaluations.index')->with('success', 'Penilaian berhasil dihapus.');
    }
}
