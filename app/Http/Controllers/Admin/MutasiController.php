<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobHistory;
use App\Services\JobVacancyService;
use Illuminate\Http\Request;

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
}
