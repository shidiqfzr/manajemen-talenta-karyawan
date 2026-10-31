<?php

namespace App\Http\Controllers\Admin\Master\Concerns;

use App\Exceptions\FormasiRuleViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RespondsToFormasiViolations
{
    /**
     * Pelanggaran aturan master formasi: pesan aman dikembalikan ke admin (JSON 422 atau redirect dengan error).
     */
    protected function formasiViolation(Request $request, FormasiRuleViolationException $e): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return redirect()->back()->withInput()->with('error', $e->getMessage());
    }
}
