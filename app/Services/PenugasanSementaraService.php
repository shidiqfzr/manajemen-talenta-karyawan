<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobHistory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PenugasanSementaraService
{
    /**
     * Tambah penugasan sementara (Plt/Pjs) untuk seorang pegawai.
     * Tidak mengubah tabel `employees` utama, hanya merekam ke `job_histories`.
     */
    public function tambahPenugasan(Employee $employee, array $data): JobHistory
    {
        // Validasi status penugasan agar hanya menerima Plt/Pjs atau sejenisnya
        $statusPenugasan = $data['status_penugasan'] ?? 'Pejabat Sementara (Pjs)';
        if (! in_array($statusPenugasan, ['Pejabat Sementara (Pjs)', 'Plt', 'Diperbantukan'])) {
            throw new InvalidArgumentException('Status penugasan sementara tidak valid.');
        }

        return DB::transaction(function () use ($employee, $data, $statusPenugasan) {
            $history = JobHistory::create([
                'employee_nik' => $employee->nik,
                'jabatan' => $data['jabatan'],
                'unit_kerja' => $data['unit_kerja'],
                'level' => $data['level'] ?? $employee->level,
                'golongan' => $data['golongan'] ?? $employee->golongan,
                'status_penugasan' => $statusPenugasan,
                'tmt_awal' => $data['tmt_awal'],
                'tmt_akhir' => null, // Aktif
                'jenis_mutasi' => 'PENUGASAN_SEMENTARA',
                'nomor_sk' => $data['nomor_sk'] ?? null,
                'tanggal_sk' => $data['tanggal_sk'] ?? null,
                'catatan' => $data['catatan'] ?? null,
            ]);

            return $history;
        });
    }

    /**
     * Cabut/akhiri penugasan sementara dengan mengisi tmt_akhir.
     */
    public function cabutPenugasan(JobHistory $jobHistory, string $tanggalAkhir): JobHistory
    {
        if ($jobHistory->tmt_akhir !== null) {
            throw new InvalidArgumentException('Penugasan sementara ini sudah diakhiri sebelumnya.');
        }

        if ($jobHistory->status_penugasan === 'Definitif') {
            throw new InvalidArgumentException('Tidak dapat mencabut jabatan Definitif melalui metode ini.');
        }

        return DB::transaction(function () use ($jobHistory, $tanggalAkhir) {
            $jobHistory->update([
                'tmt_akhir' => $tanggalAkhir,
            ]);

            return $jobHistory;
        });
    }

    /**
     * Ambil daftar penugasan sementara yang sedang aktif untuk NIK tertentu.
     */
    public function getPenugasanAktif(string $nik)
    {
        return JobHistory::where('employee_nik', $nik)
            ->whereNull('tmt_akhir')
            ->where('status_penugasan', '!=', 'Definitif')
            ->orderBy('tmt_awal', 'desc')
            ->get();
    }
}
