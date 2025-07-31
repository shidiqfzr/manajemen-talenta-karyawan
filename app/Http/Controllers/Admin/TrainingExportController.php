<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Training;
use App\Services\TrainingExportService;
use App\Exports\TrainingsExport;
use Maatwebsite\Excel\Facades\Excel;

class TrainingExportController extends Controller
{
    protected TrainingExportService $exportService;

    public function __construct(TrainingExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Export Surat Tugas to Word (.docx) format
     */
    public function exportSuratTugas(Training $training)
    {
        return $this->exportService->exportSuratTugasToWord($training);
    }

    /**
     * Export Training Data to Excel (.xlsx) format
     */
    public function exportExcel(Request $request)
    {
        $filters = $request->only(['start_date', 'end_date']);
        return Excel::download(new TrainingsExport($filters), 'data_pelatihan.xlsx');
    }
}
