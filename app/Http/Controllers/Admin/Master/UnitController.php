<?php

namespace App\Http\Controllers\Admin\Master;

use App\Exceptions\FormasiRuleViolationException;
use App\Http\Controllers\Admin\Master\Concerns\RespondsToFormasiViolations;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Unit;
use App\Services\Formasi\MasterFormasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    use RespondsToFormasiViolations;

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
    public function store(Request $request, MasterFormasiService $service)
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

        try {
            $unit = $service->createUnit(Arr::only($validated, ['nama', 'kode', 'wilayah', 'urutan']), $request->user());
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

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
    public function update(Request $request, Unit $unit, MasterFormasiService $service)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:units,nama,'.$unit->id,
            'kode' => 'nullable|string|max:50',
            'wilayah' => 'required|string|max:255',
            'urutan' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'reason' => 'nullable|string|max:1000',
        ], [
            'nama.unique' => 'Nama unit kerja ini sudah digunakan oleh unit lain.',
        ]);

        $active = $request->has('is_active') ? (bool) $request->input('is_active') : false;

        try {
            DB::transaction(function () use ($service, $unit, $validated, $active, $request): void {
                $service->updateUnit($unit, Arr::only($validated, ['nama', 'kode', 'wilayah', 'urutan']), $validated['reason'] ?? null, $request->user());
                $service->setUnitActive($unit->refresh(), $active, $validated['reason'] ?? null, $request->user());
            });
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

        return redirect()->route('admin.master.units.index')
            ->with('success', "Unit Kerja '{$unit->nama}' berhasil diperbarui.");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Request $request, Unit $unit, MasterFormasiService $service)
    {
        $activate = ! $unit->is_active;

        try {
            $service->setUnitActive($unit, $activate, $request->input('reason'), $request->user());
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

        $statusText = $activate ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()
            ->with('success', "Status Unit Kerja '{$unit->nama}' berhasil {$statusText}.");
    }

    /**
     * Remove unit from database (hanya bila tidak dipakai pegawai, kursi, atau riwayat jabatan).
     */
    public function destroy(Request $request, Unit $unit, MasterFormasiService $service)
    {
        $nama = $unit->nama;

        try {
            $service->deleteUnit($unit, $request->input('reason'), $request->user());
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

        return redirect()->route('admin.master.units.index')
            ->with('success', "Unit Kerja '{$nama}' berhasil dihapus dari Master Data.");
    }
}
