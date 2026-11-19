<?php

namespace App\Http\Controllers\Admin\Master;

use App\Exceptions\FormasiRuleViolationException;
use App\Http\Controllers\Admin\Master\Concerns\RespondsToFormasiViolations;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\Formasi\MasterFormasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    use RespondsToFormasiViolations;

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

        return $this->toFormasi($request, $unit->id)
            ->with('success', "Unit Kerja '{$validated['nama']}' berhasil ditambahkan ke Master Data.");
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

        $active = $request->has('is_active') ? $request->boolean('is_active') : $unit->is_active;

        try {
            DB::transaction(function () use ($service, $unit, $validated, $active, $request): void {
                $service->updateUnit($unit, Arr::only($validated, ['nama', 'kode', 'wilayah', 'urutan']), $validated['reason'] ?? null, $request->user());
                $service->setUnitActive($unit->refresh(), $active, $validated['reason'] ?? null, $request->user());
            });
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

        return $this->toFormasi($request, $unit->id)
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
     * Remove unit from database (hanya bila tidak dipakai karyawan, formasi jabatan, atau riwayat jabatan).
     */
    public function destroy(Request $request, Unit $unit, MasterFormasiService $service)
    {
        $nama = $unit->nama;

        try {
            $service->deleteUnit($unit, $request->input('reason'), $request->user());
        } catch (FormasiRuleViolationException $e) {
            return $this->formasiViolation($request, $e);
        }

        return $this->toFormasi($request, 0)
            ->with('success', "Unit Kerja '{$nama}' berhasil dihapus dari Master Data.");
    }
}
