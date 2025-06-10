<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeeStatistic;

class EmployeeStatisticController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $statistics = EmployeeStatistic::latest()->get();
        return view('admin.employees.statistics.index', compact('statistics'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.employees.statistics.add-statistic');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'periode' => 'required|date',
            'jumlah_karpim' => 'required|integer',
            'jumlah_karpel' => 'required|integer',
        ]);

        $validated['total_karyawan'] = $validated['jumlah_karpim'] + $validated['jumlah_karpel'];

        EmployeeStatistic::create($validated);

        return redirect()->route('admin.employees.statistics.index')->with('success', 'Statistik karyawan berhasil disimpan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $statistic = EmployeeStatistic::findOrFail($id);
        return view('admin.employees.statistics.edit-statistic', compact('statistic'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'periode' => 'required|date',
            'jumlah_karpim' => 'required|integer',
            'jumlah_karpel' => 'required|integer',
        ]);

        $validated['total_karyawan'] = $validated['jumlah_karpim'] + $validated['jumlah_karpel'];

        $statistic = EmployeeStatistic::findOrFail($id);
        $statistic->update($validated);

        return redirect()->route('admin.employees.statistics.index')->with('success', 'Statistik berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $statistic = EmployeeStatistic::findOrFail($id);
        $statistic->delete();

        return redirect()->route('admin.employees.statistics.index')->with('success', 'Statistik berhasil dihapus.');
    }
}
