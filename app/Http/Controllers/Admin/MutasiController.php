<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobHistory;
use App\Services\JobVacancyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MutasiController extends Controller
{
    /**
     * Display the unified Mutasi & Penempatan Hub.
     */
    public function index(Request $request, JobVacancyService $service)
    {
        $stats = $service->getStatistics();
        $totalHistories = JobHistory::count();

        // Determine active tab: default to 'vacancies'
        $activeTab = $request->query('tab');
        if ($activeTab === 'process') {
            return redirect()->route('admin.mutasi.create', $request->except('tab'));
        }
        if (! $activeTab) {
            $activeTab = 'vacancies';
        }

        return view('admin.mutasi.index', compact('stats', 'totalHistories', 'activeTab'));
    }

    /**
     * Show the dedicated form for creating a new Mutasi & Penempatan.
     */
    public function create(Request $request)
    {
        return view('admin.mutasi.create');
    }

    /**
     * Backward compatibility redirect for old history route.
     */
    public function history()
    {
        return redirect()->route('admin.mutasi.index', ['tab' => 'history']);
    }

    /**
     * Authorized secure stream download for SK document (SECURITY.md §4.4).
     */
    public function downloadSk(JobHistory $jobHistory)
    {
        if (! $jobHistory->file_sk) {
            abort(404, 'Berkas SK tidak ditemukan.');
        }

        $filename = 'SK_'.preg_replace('/[^A-Za-z0-9_\-]/', '_', $jobHistory->nomor_sk ?? 'Mutasi').'.pdf';

        // 1. Check local private disk first (SECURITY.md §4.4)
        if (Storage::disk('local')->exists($jobHistory->file_sk)) {
            return Storage::disk('local')->response($jobHistory->file_sk, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        // 2. Fallback to public disk for legacy uploads
        if (Storage::disk('public')->exists($jobHistory->file_sk)) {
            return Storage::disk('public')->response($jobHistory->file_sk, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        abort(404, 'Berkas fisik SK tidak ditemukan di penyimpanan server.');
    }
}
