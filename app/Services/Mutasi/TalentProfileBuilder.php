<?php

namespace App\Services\Mutasi;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use App\Services\ManPowerPlanningService;
use Carbon\Carbon;

/**
 * Talent intelligence profile of an employee shown next to a mutation:
 * 9-box, SMKBK/CLI, tenure, retirement countdown and L&D history.
 */
class TalentProfileBuilder
{
    public function __construct(
        private readonly ManPowerPlanningService $planning,
        private readonly MutasiGovernanceGuard $guard,
    ) {}

    /** Single duration format for every tenure text on the mutation pages, e.g. 19 → "1 th 7 bln". */
    public static function formatMonths(int $months): string
    {
        return intdiv($months, 12).' th '.($months % 12).' bln';
    }

    /**
     * Retrieve comprehensive talent intelligence profile for an employee
     * including 9-Box, SMKBK/CLI scores, tenure in position & unit, retirement countdown, and L&D history.
     */
    public function build(string $nik): ?array
    {
        $emp = Employee::with([
            'currentJob',
            'evaluations' => fn ($q) => $q->latest(),
            'trainings' => fn ($q) => $q->orderByDesc('tanggal_mulai')->take(5),
        ])->where('nik', $nik)->first();

        if (! $emp) {
            return null;
        }

        $rmLevel = $this->planning->determineRmLevel($emp);
        $bidang = $this->planning->determineBidang($emp);

        // 1. Tenure Masa Dinas Jabatan
        $tmtJabatan = $emp->tanggal_dalam_jabatan ?? $emp->currentJob?->tmt_awal;
        $tenureJabatanFormatted = '—';
        if ($tmtJabatan) {
            $tenureJabatanFormatted = self::formatMonths((int) Carbon::parse($tmtJabatan)->diffInMonths(now()));
        }

        // 2. Tenure Masa Dinas Unit Kerja
        $tmtUnit = $emp->tmt_unit_kerja;
        $tenureUnitFormatted = '—';
        $isTenureUnitCompliant = true;
        $totalMonthsU = 0;
        if ($tmtUnit) {
            $carbonUnit = Carbon::parse($tmtUnit);
            $totalMonthsU = (int) $carbonUnit->diffInMonths(now());
            $tenureUnitFormatted = self::formatMonths($totalMonthsU);
            $isTenureUnitCompliant = $totalMonthsU >= MutasiPolicy::MIN_UNIT_TENURE_MONTHS;
        } else {
            $isTenureUnitCompliant = false;
        }

        // 3. Total Masa Kerja Perusahaan
        $totalMasaKerjaFormatted = '—';
        if ($emp->tmt_bekerja) {
            $totalMasaKerjaFormatted = self::formatMonths((int) Carbon::parse($emp->tmt_bekerja)->diffInMonths(now()));
        }

        // 4. Usia & Proyeksi Pensiun (56 Th) / MBT (55 Th)
        $usiaFormatted = $emp->tanggal_lahir ? Carbon::parse($emp->tanggal_lahir)->age.' th' : '—';
        $sisaPensiunFormatted = '—';
        $isMendekatiPensiun = false;
        if ($emp->tanggal_pensiun) {
            $carbonPensiun = Carbon::parse($emp->tanggal_pensiun);
            if ($carbonPensiun->isFuture()) {
                $diffYears = (int) now()->diffInYears($carbonPensiun);
                $diffMonths = (int) now()->diffInMonths($carbonPensiun) % 12;
                $sisaPensiunFormatted = "{$diffYears} th {$diffMonths} bln lagi";
                $isMendekatiPensiun = $diffYears < MutasiPolicy::RETIREMENT_WARNING_YEARS;
            } else {
                $sisaPensiunFormatted = 'Telah mencapai batas pensiun';
                $isMendekatiPensiun = true;
            }
        }

        // 5. Matriks Talenta (9-Box) & Evaluasi Kinerja
        $latestEval = $emp->evaluations->first();
        $kategori9Box = $latestEval?->kategori_9box;
        $skorKinerja = $latestEval?->skor_smkbk_9box ?? $latestEval?->hasil_skor_asesmen;
        $skorPotensi = $latestEval?->skor_cli_9box;
        $lembagaAsesmen = $latestEval?->lembaga_asesmen;
        $tglAsesmen = $latestEval?->tanggal_pelaksanaan_asesmen ? Carbon::parse($latestEval->tanggal_pelaksanaan_asesmen)->translatedFormat('d M Y') : null;
        $expiredAsesmen = $latestEval?->expired_asesmen;
        $isAsesmenExpired = $expiredAsesmen ? Carbon::parse($expiredAsesmen)->isPast() : false;
        $expiredAsesmenFormatted = $expiredAsesmen ? Carbon::parse($expiredAsesmen)->translatedFormat('d M Y') : null;

        // 6. SK Terakhir
        $lastJob = $emp->currentJob;
        $lastSkNomor = $lastJob?->nomor_sk;
        $lastSkTanggal = $lastJob?->tanggal_sk ? Carbon::parse($lastJob->tanggal_sk)->translatedFormat('d M Y') : null;
        $lastJobId = $lastJob?->id;
        $hasLastSkFile = (bool) $lastJob?->file_sk;

        // 7. Riwayat Pelatihan Terbaru
        $trainings = $emp->trainings->map(function ($tr) {
            return [
                'judul' => $tr->judul,
                'tanggal_mulai' => $tr->tanggal_mulai ? Carbon::parse($tr->tanggal_mulai)->translatedFormat('d M Y') : '—',
                'penyelenggara' => $tr->penyelenggara ?: 'Internal Holding',
                'has_sertifikat' => ! empty($tr->pivot->sertifikat),
                'sertifikat_path' => $tr->pivot->sertifikat ?? null,
            ];
        })->toArray();

        return [
            'nik' => $emp->nik,
            'nama' => $emp->nama,
            'foto' => $emp->foto,
            'jabatan' => $emp->currentJob?->jabatan ?? $emp->jabatan,
            'unit_kerja' => $emp->currentJob?->unit_kerja ?? $emp->unit_kerja,
            'level' => $emp->currentJob?->level ?? $emp->level,
            'is_karpim' => $emp->is_karpim,
            'rm_level' => $rmLevel,
            'bidang' => $bidang,
            'golongan' => $emp->golongan,
            'job_grade' => $emp->job_grade,
            'person_grade' => $emp->person_grade,
            'has_job' => (bool) $emp->currentJob,
            'tmt_unit_kerja' => $emp->tmt_unit_kerja?->toDateString(),
            'tanggal_dalam_jabatan' => $emp->tanggal_dalam_jabatan?->toDateString(),
            'tenure_jabatan_formatted' => $tenureJabatanFormatted,
            'tenure_unit_formatted' => $tenureUnitFormatted,
            'tenure_unit_months' => $totalMonthsU,
            'is_tenure_unit_compliant' => $isTenureUnitCompliant,
            'total_masa_kerja_formatted' => $totalMasaKerjaFormatted,
            'usia_formatted' => $usiaFormatted,
            'tanggal_pensiun_formatted' => $emp->tanggal_pensiun?->translatedFormat('d M Y'),
            'sisa_pensiun_formatted' => $sisaPensiunFormatted,
            'is_mendekati_pensiun' => $isMendekatiPensiun,
            'is_retired' => $this->guard->isRetired($emp),
            'kategori_9box' => $kategori9Box,
            'skor_kinerja' => $skorKinerja,
            'skor_potensi' => $skorPotensi,
            'lembaga_asesmen' => $lembagaAsesmen,
            'tanggal_asesmen_formatted' => $tglAsesmen,
            'is_asesmen_expired' => $isAsesmenExpired,
            'expired_asesmen_formatted' => $expiredAsesmenFormatted,
            'last_sk_nomor' => $lastSkNomor,
            'last_sk_tanggal' => $lastSkTanggal,
            'last_job_id' => $lastJobId,
            'has_last_sk_file' => $hasLastSkFile,
            'trainings' => $trainings,
            'has_scheduled_mutation' => (bool) $emp->latestScheduledJob,
            'scheduled_mutation' => $emp->latestScheduledJob ? [
                'id' => $emp->latestScheduledJob->id,
                'jabatan' => $emp->latestScheduledJob->jabatan,
                'unit_kerja' => $emp->latestScheduledJob->unit_kerja,
                'jenis_mutasi' => $emp->latestScheduledJob->jenis_mutasi,
                'tmt_awal' => $emp->latestScheduledJob->tmt_awal?->toDateString(),
                'tmt_awal_formatted' => $emp->latestScheduledJob->tmt_awal ? Carbon::parse($emp->latestScheduledJob->tmt_awal)->translatedFormat('d M Y') : '—',
                'nomor_sk' => $emp->latestScheduledJob->nomor_sk,
            ] : null,
        ];
    }
}
