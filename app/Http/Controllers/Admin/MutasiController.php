<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobHistory;
use App\Models\MutasiChainProposal;
use App\Services\JobVacancyService;
use App\Services\ManPowerPlanningService;
use App\Support\HrisPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MutasiController extends Controller
{
    /**
     * Display the unified Mutasi & Penempatan Hub.
     */
    public function index(Request $request, JobVacancyService $service, ManPowerPlanningService $mpp)
    {
        $stats = $service->getStatistics();
        // Lencana tab MPP mengikuti kursi master formasi, bukan angka tetap.
        $mppFormasiTotal = $mpp->totalStandarFormasi();
        $totalHistories = JobHistory::count();

        // Usulan Mutasi Berantai tab: preparers see their own, approvers see every submitted proposal
        $canSeeProposals = Gate::any([HrisPermission::MUTASI_PREPARE, HrisPermission::MUTASI_APPROVE]);
        $pendingProposals = $canSeeProposals
            ? MutasiChainProposal::where('status', MutasiChainProposal::STATUS_PENDING)
                ->when(! Gate::allows(HrisPermission::MUTASI_APPROVE), fn ($q) => $q->where('created_by', $request->user()->id))
                ->count()
            : 0;

        // Determine active tab: default to 'vacancies'
        $activeTab = $request->query('tab');
        if ($activeTab === 'process') {
            return redirect()->route('admin.mutasi.create', $request->except('tab'));
        }
        if (! $activeTab || ($activeTab === 'usulan' && ! $canSeeProposals)) {
            $activeTab = 'vacancies';
        }

        return view('admin.mutasi.index', compact('stats', 'totalHistories', 'activeTab', 'mppFormasiTotal', 'canSeeProposals', 'pendingProposals'));
    }

    /**
     * Show the dedicated form for creating a new Mutasi & Penempatan.
     */
    public function create(Request $request)
    {
        return view('admin.mutasi.create');
    }

    /**
     * Show the dedicated form for creating a multi-employee Chain Transfer.
     */
    public function chain(Request $request)
    {
        return view('admin.mutasi.chain');
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
