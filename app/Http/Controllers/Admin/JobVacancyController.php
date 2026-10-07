<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class JobVacancyController extends Controller
{
    /**
     * Redirect to the unified Mutasi & Penempatan Hub on the 'vacancies' tab.
     */
    public function index(Request $request)
    {
        return redirect()->route('admin.mutasi.index', array_merge(['tab' => 'vacancies'], $request->query()));
    }
}
