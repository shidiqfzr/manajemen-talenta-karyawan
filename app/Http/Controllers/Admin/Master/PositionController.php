<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    /**
     * Display a listing of master positions/jabatans.
     */
    public function index(Request $request)
    {
        $query = Position::query();

        // Search filter
        if ($search = $request->input('search')) {
            $query->where('nama', 'like', "%{$search}%");
        }

        // Bidang filter
        if ($bidang = $request->input('bidang')) {
            $query->where('bidang', $bidang);
        }

        // Level filter
        if ($level = $request->input('level')) {
            $query->where('level', $level);
        }

        // RM Level filter
        if ($rmLevel = $request->input('rm_level')) {
            $query->where('rm_level', $rmLevel);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === '1');
        }

        // Metrics
        $totalPositions = Position::count();
        $karpimCount = Position::where('level', 'Karpim')->count();
        $karpelCount = Position::where('level', 'Karpel')->count();
        $activeCount = Position::where('is_active', true)->count();

        // Reference lists
        $bidangList = Position::BIDANG_LIST;
        $levelList = Position::LEVEL_LIST;
        $rmLevelList = Position::RM_LEVEL_LIST;

        $positions = $query->orderBy('level', 'asc')
            ->orderBy('bidang', 'asc')
            ->orderBy('nama', 'asc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.master.positions.index', compact(
            'positions',
            'totalPositions',
            'karpimCount',
            'karpelCount',
            'activeCount',
            'bidangList',
            'levelList',
            'rmLevelList'
        ));
    }

    /**
     * Show form to create new position.
     */
    public function create()
    {
        $bidangList = Position::BIDANG_LIST;
        $levelList = Position::LEVEL_LIST;
        $rmLevelList = Position::RM_LEVEL_LIST;

        return view('admin.master.positions.create', compact('bidangList', 'levelList', 'rmLevelList'));
    }

    /**
     * Store new position.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:positions,nama',
            'bidang' => 'nullable|string|in:TAN,TEK,KEU,UMU',
            'level' => 'required|string|in:Karpim,Karpel',
            'rm_level' => 'nullable|string|in:RM-1,RM-2,RM-3',
            'is_active' => 'nullable|boolean',
        ], [
            'nama.unique' => 'Nama jabatan formasi ini sudah terdaftar dalam sistem.',
        ]);

        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : true;

        // Reset rm_level if Karpel
        if ($validated['level'] === 'Karpel') {
            $validated['rm_level'] = null;
        }

        $position = Position::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Jabatan '{$position->nama}' berhasil ditambahkan ke Master Data.",
                'position' => $position,
            ], 201);
        }

        return redirect()->route('admin.master.positions.index')
            ->with('success', "Jabatan '{$validated['nama']}' berhasil ditambahkan ke Master Data.");
    }

    /**
     * Show form to edit existing position.
     */
    public function edit(Position $position)
    {
        $bidangList = Position::BIDANG_LIST;
        $levelList = Position::LEVEL_LIST;
        $rmLevelList = Position::RM_LEVEL_LIST;
        $employeeCount = Employee::where('jabatan', $position->nama)->count();

        return view('admin.master.positions.edit', compact(
            'position',
            'bidangList',
            'levelList',
            'rmLevelList',
            'employeeCount'
        ));
    }

    /**
     * Update existing position.
     */
    public function update(Request $request, Position $position)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:positions,nama,'.$position->id,
            'bidang' => 'nullable|string|in:TAN,TEK,KEU,UMU',
            'level' => 'required|string|in:Karpim,Karpel',
            'rm_level' => 'nullable|string|in:RM-1,RM-2,RM-3',
            'is_active' => 'nullable|boolean',
        ], [
            'nama.unique' => 'Nama jabatan ini sudah digunakan oleh posisi lain.',
        ]);

        $oldName = $position->nama;
        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : false;

        if ($validated['level'] === 'Karpel') {
            $validated['rm_level'] = null;
        }

        $position->update($validated);

        // Keep denormalized string consistent in employees table
        if ($oldName !== $validated['nama']) {
            Employee::where('jabatan', $oldName)->update(['jabatan' => $validated['nama']]);
        }

        return redirect()->route('admin.master.positions.index')
            ->with('success', "Jabatan '{$position->nama}' berhasil diperbarui.");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Position $position)
    {
        $position->is_active = ! $position->is_active;
        $position->save();

        $statusText = $position->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()
            ->with('success', "Status Jabatan '{$position->nama}' berhasil {$statusText}.");
    }

    /**
     * Remove position from database (Protected if referenced by employees).
     */
    public function destroy(Position $position)
    {
        $employeeCount = Employee::where('jabatan', $position->nama)->count();

        if ($employeeCount > 0) {
            return redirect()->back()
                ->with('error', "Jabatan '{$position->nama}' tidak dapat dihapus karena masih tercatat pada {$employeeCount} pegawai. Silakan gunakan fitur Nonaktifkan.");
        }

        $nama = $position->nama;
        $position->delete();

        return redirect()->route('admin.master.positions.index')
            ->with('success', "Jabatan '{$nama}' berhasil dihapus dari Master Data.");
    }
}
