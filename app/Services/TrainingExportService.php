<?php

namespace App\Services;

use App\Models\Training;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TrainingExportService
{
    public function exportSuratTugasToWord(Training $training)
    {
        try {
            $participants = $training->employees;

            $templatePath = resource_path('templates/surat-tugas-template.docx');
            $templateProcessor = new TemplateProcessor($templatePath);

            // Basic info placeholders
            $templateProcessor->setValue('judul', $this->escape($training->judul));
            $templateProcessor->setValue('tanggal_mulai', $this->formatDate($training->tanggal_mulai));
            $templateProcessor->setValue('tanggal_akhir', $this->formatDate($training->tanggal_akhir));
            $templateProcessor->setValue('metode', $this->escape($training->metode));
            $templateProcessor->setValue('nomor_surat', $this->escape($training->nomor_surat));
            $templateProcessor->setValue('tanggal_surat', $this->formatDate($training->tanggal_surat));
            $templateProcessor->setValue('penyelenggara', $this->escape($training->penyelenggara));
            $templateProcessor->setValue('biaya_tiket_peserta', number_format($training->biaya_tiket_peserta, 0, ',', '.'));

            // Export participants
            $this->fillParticipants($templateProcessor, $participants);

            // Save & return
            $fileName = 'Surat_Tugas_' . Str::slug($training->judul) . '.docx';
            $savePath = storage_path("app/public/exports/{$fileName}");

            $templateProcessor->saveAs($savePath);
            return response()->download($savePath)->deleteFileAfterSend();

        } catch (\Throwable $e) {
            Log::error('Export Surat Tugas Word Error: ' . $e->getMessage());
            abort(500, 'Gagal mengekspor Surat Tugas. Silakan hubungi administrator.');
        }
    }

    /**
     * Escape XML special characters
     */
    protected function escape(string|null $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format date to Indonesian format
     */
    protected function formatDate(string|null $date): string
    {
        return $date ? Carbon::parse($date)->translatedFormat('d F Y') : '-';
    }

    /**
     * Clone participant rows and bind values
     */
    protected function fillParticipants(TemplateProcessor $templateProcessor, $participants): void
    {
        $count = $participants->count();
        $templateProcessor->cloneRow('nik', $count);

        foreach ($participants as $index => $participant) {
            $i = $index + 1;
            $templateProcessor->setValue("no#{$i}", $i);
            $templateProcessor->setValue("nik#{$i}", $this->escape($participant->nik));
            $templateProcessor->setValue("nama#{$i}", $this->escape($participant->nama));
            $templateProcessor->setValue("jabatan#{$i}", $this->escape($participant->jabatan));
            $templateProcessor->setValue("unit#{$i}", $this->escape($participant->unit_kerja));
        }
    }
}
