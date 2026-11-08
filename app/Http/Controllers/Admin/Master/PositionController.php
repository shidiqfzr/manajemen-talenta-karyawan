<?php

namespace App\Http\Controllers\Admin\Master;

use App\Exceptions\FormasiRuleViolationException;
use App\Http\Controllers\Admin\Master\Concerns\RespondsToFormasiViolations;
use App\Http\Controllers\Controller;
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
     * Store new position.
     */
    public function store(Request $request, MasterFormasiService $service)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:positions,nama',
            'bidang' => 'nullable|string|in:TAN,TEK,KEU,UMU',
            'level' => 'required|string|in:Karpim,Karpel',
            'rm_level' => 'nullable|string|in:RM-1,RM-2,RM-3,RM-4,RM-5,RM-6,RM-7',
            'is_active' => 'nullable|boolean',
            'unit_nama' => 'nullable|string|exists:units,nama',
            'kuota' => 'nullable|integer|min:1|max:1000',
        ], [
            'nama.unique' => 'Nama jabatan formasi ini sudah terdaftar dalam sistem.',
        ]);

        $seatUnitId = null;

        try {
            $position = DB::transaction(function () use ($service, $validated, $request, &$seatUnitId): Position {
                $position = $service->createPosition(Arr::only($validated, ['nama', 'bidang', 'level', 'rm_level']), $request->user());

                if (! empty($validated['unit_nama'])) {
                    $unit = Unit::where('nama', $validated['unit_nama'])->firstOrFail();
                    $seatUnitId = $unit->id;
                    $service->addSeat($unit, $position, (int) ($validated['kuota'] ?? 1), $validated['bidang'] ?? null, $request->user());
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

        return $this->toFormasi($request, $seatUnitId)
            ->with('success', "Jabatan '{$validated['nama']}' berhasil ditambahkan ke Master Data.");
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
            'rm_level' => 'nullable|string|in:RM-1,RM-2,RM-3,RM-4,RM-5,RM-6,RM-7',
            'is_active' => 'nullable|boolean',
            'reason' => 'nullable|string|max:1000',
        ], [
            'nama.unique' => 'Nama jabatan ini sudah digunakan oleh posisi lain.',
        ]);

        $active = $request->has('is_active') ? $request->boolean('is_active') : $position->is_active;

        try {
            DB::transaction(function () use ($service, $position, $validated, $active, $request): void {
                $service->updatePosition($position, Arr::only($validated, ['nama', 'bidang', 'level', 'rm_level']), $validated['reason'] ?? null, $request->user());
                $service->setPositionActive($position->refresh(), $active, $validated['reason'] ?? null, $request->user());
            });
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

        return $this->toFormasi($request)
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
     * Remove position from database (hanya bila tidak dipakai karyawan, formasi jabatan, atau riwayat jabatan).
     */
    public function destroy(Request $request, Position $position, MasterFormasiService $service)
    {
        $nama = $position->nama;

        try {
            $service->deletePosition($position, $request->input('reason'), $request->user());
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

        return $this->toFormasi($request)
            ->with('success', "Jabatan '{$nama}' berhasil dihapus dari Master Data.");
    }
}
