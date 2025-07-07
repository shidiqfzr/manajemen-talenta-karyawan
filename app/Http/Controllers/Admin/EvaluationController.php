<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Employee;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    // Tampilkan daftar evaluasi
    public function index()
    {
        $evaluations = Evaluation::with('employee')->latest()->paginate(20);;
        return view('admin.evaluations.index', compact('evaluations'));
    }

    // Tampilkan form tambah evaluasi
    public function create($nik = null)
    {
        if ($nik) {
            $employee = Employee::where('nik', $nik)->firstOrFail();
            return view('admin.evaluations.add-evaluation', compact('employee'));
        }

        $employees = Employee::all(); // untuk dropdown pilih karyawan
        return view('admin.evaluations.add-evaluation', compact('employees'));
    }

    // Simpan data evaluasi
    public function store(Request $request)
    {
        $request->validate([
            'nik' => 'required|exists:employees,nik',
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

        $nilaiTertimbang =
            ($request->nilai_kepemimpinan * 0.4) +
            ($request->nilai_perilaku_budaya * 0.3) +
            ($request->nilai_pengalaman_teknis * 0.2) +
            ($request->nilai_kematangan_pribadi * 0.1);

        Evaluation::create($request->only([
            'nik',
            'nilai_kepemimpinan',
            'nilai_perilaku_budaya',
            'nilai_pengalaman_teknis',
            'nilai_kematangan_pribadi',
            'skor_smkbk_9box',
            'skor_cli_9box',
            'kategori_9box',
            'bidang_tugas',
            'lembaga_asesmen',
            'tanggal_pelaksanaan_asesmen',
            'hasil_skor_asesmen',
            'kategori_asesmen',
            'keterangan_asesmen',
            'expired_asesmen',
            'keterangan',
        ]) + ['nilai_tertimbang' => $nilaiTertimbang]);

        return redirect()->route('admin.evaluations.index')->with('success', 'Penilaian berhasil disimpan.');
    }

    public function show(Evaluation $evaluation)
    {
        $employee = Employee::where('nik', $evaluation->nik)->first();
        return view('admin.evaluations.show', compact('evaluation', 'employee'));
    }

    // Tampilkan form edit evaluasi
    public function edit(Evaluation $evaluation)
    {
        $employee = Employee::where('nik', $evaluation->nik)->first();
        return view('admin.evaluations.edit-evaluation', compact('evaluation', 'employee'));
    }

    // Update evaluasi
    public function update(Request $request, Evaluation $evaluation)
    {
        $request->validate([
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

        $nilaiTertimbang =
            ($request->nilai_kepemimpinan * 0.4) +
            ($request->nilai_perilaku_budaya * 0.3) +
            ($request->nilai_pengalaman_teknis * 0.2) +
            ($request->nilai_kematangan_pribadi * 0.1);

        $evaluation->update($request->only([
            'nilai_kepemimpinan',
            'nilai_perilaku_budaya',
            'nilai_pengalaman_teknis',
            'nilai_kematangan_pribadi',
            'skor_smkbk_9box',
            'skor_cli_9box',
            'kategori_9box',
            'bidang_tugas',
            'tanggal_diangkat_staf',
            'masa_kerja_tahun',
            'masa_kerja_bulan',
            'lembaga_asesmen',
            'tanggal_pelaksanaan_asesmen',
            'hasil_skor_asesmen',
            'kategori_asesmen',
            'keterangan_asesmen',
            'expired_asesmen',
            'keterangan',
        ]) + ['nilai_tertimbang' => $nilaiTertimbang]);

        return redirect()->route('admin.evaluations.index')->with('success', 'Penilaian berhasil diperbarui.');
    }

    // Hapus evaluasi
    public function destroy(Evaluation $evaluation)
    {
        $evaluation->delete();
        return redirect()->route('admin.evaluations.index')->with('success', 'Penilaian berhasil dihapus.');
    }
}
