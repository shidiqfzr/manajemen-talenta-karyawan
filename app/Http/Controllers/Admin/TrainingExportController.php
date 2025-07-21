<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Training;
use App\Services\TrainingExportService;

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

}
