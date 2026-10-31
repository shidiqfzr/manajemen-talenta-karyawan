<?php

namespace App\Http\Controllers\Admin\Master;

use App\Exceptions\FormasiRuleViolationException;
use App\Http\Controllers\Admin\Master\Concerns\RespondsToFormasiViolations;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use App\Services\Formasi\MasterFormasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PositionController extends Controller
{
    use RespondsToFormasiViolations;

    /**
     * Display a listing of master positions/jabatans.
     */
    public function index(Request $request)
    {
        $query = Position::query()->with('units');

        // Search filter
        if ($search = $request->input('search')) {
            $query->where('nama', 'like', "%{$search}%");
        }

        // Bidang filter (bidang melekat pada kursi di unit, bukan pada nama jabatan)
        if ($bidang = $request->input('bidang')) {
            $query->whereHas('units', fn ($units) => $units->where('position_unit.bidang', $bidang));
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
    public function store(Request $request, MasterFormasiService $service)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:positions,nama',
            'bidang' => 'nullable|string|in:TAN,TEK,KEU,UMU',
            'level' => 'required|string|in:Karpim,Karpel',
            'rm_level' => 'nullable|string|in:RM-1,RM-2,RM-3',
            'is_active' => 'nullable|boolean',
            'unit_nama' => 'nullable|string|exists:units,nama',
        ], [
            'nama.unique' => 'Nama jabatan formasi ini sudah terdaftar dalam sistem.',
        ]);

        try {
            $position = DB::transaction(function () use ($service, $validated, $request): Position {
                $position = $service->createPosition(Arr::only($validated, ['nama', 'bidang', 'level', 'rm_level']), $request->user());

                if (! empty($validated['unit_nama'])) {
                    $unit = Unit::where('nama', $validated['unit_nama'])->firstOrFail();
                    $service->addSeat($unit, $position, 1, $validated['bidang'] ?? null, $request->user());
                }

                return $position;
            });
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

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
    public function update(Request $request, Position $position, MasterFormasiService $service)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:positions,nama,'.$position->id,
            'bidang' => 'nullable|string|in:TAN,TEK,KEU,UMU',
            'level' => 'required|string|in:Karpim,Karpel',
            'rm_level' => 'nullable|string|in:RM-1,RM-2,RM-3',
            'is_active' => 'nullable|boolean',
            'reason' => 'nullable|string|max:1000',
        ], [
            'nama.unique' => 'Nama jabatan ini sudah digunakan oleh posisi lain.',
        ]);

        $active = $request->has('is_active') ? (bool) $request->input('is_active') : false;

        try {
            DB::transaction(function () use ($service, $position, $validated, $active, $request): void {
                $service->updatePosition($position, Arr::only($validated, ['nama', 'bidang', 'level', 'rm_level']), $validated['reason'] ?? null, $request->user());
                $service->setPositionActive($position->refresh(), $active, $validated['reason'] ?? null, $request->user());
            });
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

        return redirect()->route('admin.master.positions.index')
            ->with('success', "Jabatan '{$position->nama}' berhasil diperbarui.");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Request $request, Position $position, MasterFormasiService $service)
    {
        $activate = ! $position->is_active;

        try {
            $service->setPositionActive($position, $activate, $request->input('reason'), $request->user());
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

        $statusText = $activate ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()
            ->with('success', "Status Jabatan '{$position->nama}' berhasil {$statusText}.");
    }

    /**
     * Remove position from database (hanya bila tidak dipakai pegawai, kursi, atau riwayat jabatan).
     */
    public function destroy(Request $request, Position $position, MasterFormasiService $service)
    {
        $nama = $position->nama;

        try {
            $service->deletePosition($position, $request->input('reason'), $request->user());
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

        return redirect()->route('admin.master.positions.index')
            ->with('success', "Jabatan '{$nama}' berhasil dihapus dari Master Data.");
    }
}
