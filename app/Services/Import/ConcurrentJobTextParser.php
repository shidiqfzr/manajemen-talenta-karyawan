<?php

namespace App\Services\Import;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use Carbon\Carbon;

class ConcurrentJobTextParser
{
    private const INDO_MONTHS = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
        'jun' => 6, 'jul' => 7, 'agu' => 8, 'agt' => 8,
        'sep' => 9, 'okt' => 10, 'nov' => 11, 'des' => 12,
    ];

    private ?array $allUnitNames = null;

    private ?array $allPositionNames = null;

    /**
     * Mem-parsing teks tugas rangkap di Kolom O untuk mengekstrak status penugasan (Plt/Pjs),
     * tanggal TMT, unit kerja penugasan, dan nama jabatan yang diemban.
     */
    public function parse(string $text, string $defaultUnit = ''): ?array
    {
        $text = trim($text);
        if (empty($text)) {
            return null;
        }

        // 1. Ekstrak Status Penugasan
        $status = 'Plt';
        if (preg_match('/pjs|pejabat\s+sementara/i', $text)) {
            $status = 'Pejabat Sementara (Pjs)';
        } elseif (preg_match('/diperbantukan/i', $text)) {
            $status = 'Diperbantukan';
        }

        // 2. Ekstrak Tanggal TMT
        $tmt = null;
        if (preg_match('/tmt\s*:?\s*(\d{1,2})\s+([a-zA-Z]+)\s+(\d{4})/i', $text, $m)) {
            $day = (int) $m[1];
            $monthStr = strtolower($m[2]);
            $year = (int) $m[3];
            $month = self::INDO_MONTHS[$monthStr] ?? null;
            if ($month) {
                $tmt = sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        } elseif (preg_match('/tmt\s*:?\s*(\d{4}-\d{2}-\d{2})/i', $text, $m)) {
            $tmt = $m[1];
        }

        // 3. Ekstrak Unit Kerja
        if ($this->allUnitNames === null) {
            $this->allUnitNames = Unit::orderByRaw('LENGTH(nama) DESC')->pluck('nama')->toArray();
        }

        $matchedUnit = null;
        foreach ($this->allUnitNames as $unitName) {
            if (stripos($text, $unitName) !== false) {
                $matchedUnit = $unitName;
                break;
            }
        }

        if (! $matchedUnit) {
            $matchedUnit = $defaultUnit;
        }

        // 4. Ekstrak Jabatan
        $cleaned = $text;
        if ($tmt) {
            $cleaned = preg_replace('/tmt\s*:?.*$/i', '', $cleaned);
        }
        if ($matchedUnit && $matchedUnit !== $defaultUnit) {
            $cleaned = str_ireplace($matchedUnit, '', $cleaned);
        }
        $cleaned = preg_replace('/^(merangkap\s+(sebagai|sbg)?\s*)/i', '', $cleaned);
        $cleaned = preg_replace('/^(plt\.?|pjs\.?|pj\.?)\s*/i', '', trim($cleaned));
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

        if ($this->allPositionNames === null) {
            $this->allPositionNames = Position::orderByRaw('LENGTH(nama) DESC')->pluck('nama')->toArray();
        }

        $matchedPos = null;
        foreach ($this->allPositionNames as $posName) {
            if (strcasecmp($cleaned, $posName) === 0) {
                $matchedPos = $posName;
                break;
            }
        }

        if (! $matchedPos) {
            $matchedPos = $this->matchPosition($cleaned, $this->allPositionNames);
        }

        if (! $matchedPos) {
            $matchedPos = filled($cleaned) ? $cleaned : $text;
        }

        return [
            'status_penugasan' => $status,
            'jabatan' => $matchedPos,
            'unit_kerja' => $matchedUnit,
            'tmt_awal' => $tmt ?? now()->toDateString(),
        ];
    }

    /**
     * Memeriksa apakah penugasan sementara (parsed Kolom O) sudah tercatat identik di JobHistory aktif.
     */
    public function isMatchingExisting(string $nik, array $parsed): bool
    {
        $existingJob = JobHistory::where('employee_nik', $nik)
            ->whereNull('tmt_akhir')
            ->where('jabatan', $parsed['jabatan'])
            ->where('unit_kerja', $parsed['unit_kerja'])
            ->first();

        if (! $existingJob) {
            return false;
        }

        if (strcasecmp((string) $existingJob->status_penugasan, (string) $parsed['status_penugasan']) !== 0) {
            return false;
        }

        if (! empty($parsed['tmt_awal'])) {
            $existingTmt = $existingJob->tmt_awal ? Carbon::parse($existingJob->tmt_awal)->toDateString() : null;
            if ($existingTmt !== $parsed['tmt_awal']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Otomatis mendaftarkan penugasan sementara / tugas rangkap (Plt/Pjs) ke job_histories.
     */
    public function registerFromText(Employee $employee, string $rawText): void
    {
        $parsed = $this->parse($rawText, $employee->unit_kerja);
        if (! $parsed) {
            return;
        }

        $existingJob = JobHistory::where('employee_nik', $employee->nik)
            ->whereNull('tmt_akhir')
            ->where('jabatan', $parsed['jabatan'])
            ->where('unit_kerja', $parsed['unit_kerja'])
            ->first();

        if ($existingJob) {
            $existingJob->update([
                'status_penugasan' => $parsed['status_penugasan'],
                'tmt_awal' => $parsed['tmt_awal'],
                'catatan' => 'Tugas Rangkap via Excel Import: '.$rawText,
            ]);
        } else {
            JobHistory::create([
                'employee_nik' => $employee->nik,
                'jabatan' => $parsed['jabatan'],
                'unit_kerja' => $parsed['unit_kerja'],
                'level' => $employee->level,
                'golongan' => $employee->golongan,
                'status_penugasan' => $parsed['status_penugasan'],
                'tmt_awal' => $parsed['tmt_awal'],
                'tmt_akhir' => null,
                'jenis_mutasi' => 'PENUGASAN',
                'catatan' => 'Tugas Rangkap via Excel Import: '.$rawText,
            ]);
        }
    }

    /**
     * Pencocokan posisi toleran terhadap tanda baca dan spasi.
     */
    private function matchPosition(string $input, array $validPositions): ?string
    {
        if (in_array($input, $validPositions, true)) {
            return $input;
        }

        foreach ($validPositions as $pos) {
            if (strcasecmp($input, $pos) === 0) {
                return $pos;
            }
        }

        $clean = function (string $str) {
            $str = str_ireplace(['&', 'dan'], ' ', $str);
            $str = preg_replace('/\btanamanan\b/iu', 'tanaman', $str);
            $str = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $str);
            $str = preg_replace('/\s+/', ' ', trim($str));

            return mb_strtolower($str);
        };

        $cleanedInput = $clean($input);
        foreach ($validPositions as $pos) {
            if ($clean($pos) === $cleanedInput) {
                return $pos;
            }
        }

        return null;
    }
}
