<?php

namespace App\Services\Structure;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\StructureDecree;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Riwayat jabatan seorang karyawan terhadap versi struktur yang berlaku pada setiap penempatan (DOMAIN.md §3.7).
 *
 * Pencarian karyawan tidak pernah mengeluarkan NIK utuh (UU PDP): pilihan diwakili token terenkripsi yang hanya dapat
 * diterjemahkan kembali oleh server. Riwayat dibaca dari job_histories, tanpa menulis ulang apa pun.
 */
class EmployeePositionTimelineService
{
    public const MIN_TERM_LENGTH = 3;

    public const MAX_RESULTS = 8;

    /**
     * @return list<array{token: string, nama: string, nik_masked: string, jabatan: ?string, unit_kerja: ?string}>
     */
    public function search(string $term, int $limit = self::MAX_RESULTS): array
    {
        $term = trim($term);
        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

        // Karakter pembanding LIKE dibuat harfiah agar "%%%" tidak cocok dengan semua karyawan.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        return Employee::query()
            ->where(fn ($query) => $query->whereRaw("nama LIKE ? ESCAPE '!'", [$like])->orWhere('nik', $term))
            ->orderBy('nama')
            ->limit(min($limit, self::MAX_RESULTS))
            ->get(['nik', 'nama', 'jabatan', 'unit_kerja'])
            ->map(fn (Employee $employee): array => [
                'token' => Crypt::encryptString($employee->nik),
                'nama' => $employee->nama,
                'nik_masked' => self::maskNik($employee->nik),
                'jabatan' => $employee->jabatan,
                'unit_kerja' => $employee->unit_kerja,
            ])
            ->all();
    }

    /** NIK dari token pilihan; null bila token rusak atau karyawannya sudah tidak ada. */
    public function resolve(string $token): ?string
    {
        if ($token === '') {
            return null;
        }

        try {
            $nik = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        return Employee::query()->whereKey($nik)->exists() ? $nik : null;
    }

    /**
     * Penempatan yang sudah berlaku, terbaru dahulu, beserta SK struktur yang berlaku pada tanggal mulainya.
     * structure_sk dan in_structure null bila penempatan terjadi sebelum SK struktur pertama.
     *
     * @return list<array{jabatan: string, unit_kerja: string, status_penugasan: ?string, tmt_awal: string, tmt_akhir: ?string, jenis_mutasi: ?string, nomor_sk: ?string, sumber_label: string, structure_sk: ?string, in_structure: ?bool}>
     */
    public function timeline(string $nik): array
    {
        $versions = StructureDecree::query()
            ->where('status', StructureDecree::STATUS_APPLIED)
            ->whereNotNull('snapshot')
            ->orderByDesc('effective_date')
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->get();

        return JobHistory::query()
            ->where('employee_nik', $nik)
            ->where('is_applied', true)
            ->orderByDesc('tmt_awal')
            ->orderByDesc('id')
            ->get()
            ->map(function (JobHistory $history) use ($versions): array {
                $start = $history->tmt_awal->toDateString();
                $version = $versions->first(fn (StructureDecree $decree): bool => $decree->effective_date->toDateString() <= $start);

                return [
                    'jabatan' => $history->jabatan,
                    'unit_kerja' => $history->unit_kerja,
                    'status_penugasan' => $history->status_penugasan,
                    'tmt_awal' => $start,
                    'tmt_akhir' => $history->tmt_akhir?->toDateString(),
                    'jenis_mutasi' => $history->jenis_mutasi,
                    // Nomor SK hanya bermakna untuk mutasi resmi; baris lain tidak memiliki SK Direksi.
                    'nomor_sk' => $history->sumber === JobHistory::SUMBER_MUTASI ? $history->nomor_sk : null,
                    'sumber_label' => JobHistory::SUMBER_LABELS[$history->sumber] ?? (string) $history->sumber,
                    'structure_sk' => $version?->sk_number,
                    'in_structure' => $version === null ? null : isset($version->snapshot['units'][$history->unit_kerja]['seats'][$history->jabatan]),
                ];
            })
            ->all();
    }

    private static function maskNik(string $nik): string
    {
        return str_repeat('*', max(strlen($nik) - 3, 0)).substr($nik, -3);
    }
}
