<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Display a listing of master units.
     */
    public function index(Request $request)
    {
        $query = Unit::query();

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        // Wilayah filter
        if ($wilayah = $request->input('wilayah')) {
            $query->where('wilayah', $wilayah);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === '1');
        }

        // Metrics
        $totalUnits = Unit::count();
        $activeUnits = Unit::where('is_active', true)->count();
        $inactiveUnits = Unit::where('is_active', false)->count();

        // Distinct wilayah options
        $wilayahList = Unit::WILAYAH_LIST;

        $units = $query->orderBy('wilayah')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('admin.master.units.index', compact(
            'units',
            'totalUnits',
            'activeUnits',
            'inactiveUnits',
            'wilayahList'
        ));
    }

    /**
     * Show form to create new unit.
     */
    public function create()
    {
        $wilayahList = Unit::WILAYAH_LIST;

        return view('admin.master.units.create', compact('wilayahList'));
    }

    /**
     * Store new unit.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:units,nama',
            'kode' => 'nullable|string|max:50',
            'wilayah' => 'required|string|max:255',
            'urutan' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ], [
            'nama.unique' => 'Nama unit kerja ini sudah terdaftar dalam sistem.',
        ]);

        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : true;
        $validated['urutan'] = $validated['urutan'] ?? (Unit::max('urutan') + 1);

        $unit = Unit::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Unit Kerja '{$unit->nama}' berhasil ditambahkan ke Master Data.",
                'unit' => $unit,
            ], 201);
        }

        return redirect()->route('admin.master.units.index')
            ->with('success', "Unit Kerja '{$validated['nama']}' berhasil ditambahkan ke Master Data.");
    }

    /**
     * Show form to edit existing unit.
     */
    public function edit(Unit $unit)
    {
        $wilayahList = Unit::WILAYAH_LIST;
        $employeeCount = Employee::where('unit_kerja', $unit->nama)->count();

        return view('admin.master.units.edit', compact('unit', 'wilayahList', 'employeeCount'));
    }

    /**
     * Update existing unit.
     */
    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:units,nama,'.$unit->id,
            'kode' => 'nullable|string|max:50',
            'wilayah' => 'required|string|max:255',
            'urutan' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ], [
            'nama.unique' => 'Nama unit kerja ini sudah digunakan oleh unit lain.',
        ]);

        $oldName = $unit->nama;
        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : false;

        $unit->update($validated);

        // If name changed, optionally update linked employees to keep denormalized consistency
        if ($oldName !== $validated['nama']) {
            Employee::where('unit_kerja', $oldName)->update(['unit_kerja' => $validated['nama']]);
        }

        return redirect()->route('admin.master.units.index')
            ->with('success', "Unit Kerja '{$unit->nama}' berhasil diperbarui.");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Unit $unit)
    {
        $unit->is_active = ! $unit->is_active;
        $unit->save();

        $statusText = $unit->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()
            ->with('success', "Status Unit Kerja '{$unit->nama}' berhasil {$statusText}.");
    }

    /**
     * Remove unit from database (Protected if referenced by employees).
     */
    public function destroy(Unit $unit)
    {
        $employeeCount = Employee::where('unit_kerja', $unit->nama)->count();

        if ($employeeCount > 0) {
            return redirect()->back()
                ->with('error', "Unit Kerja '{$unit->nama}' tidak dapat dihapus karena masih tercatat pada {$employeeCount} pegawai. Silakan gunakan fitur Nonaktifkan.");
        }

        $nama = $unit->nama;
        $unit->delete();

        return redirect()->route('admin.master.units.index')
            ->with('success', "Unit Kerja '{$nama}' berhasil dihapus dari Master Data.");
    }
}
