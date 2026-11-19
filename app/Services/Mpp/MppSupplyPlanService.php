<?php

namespace App\Services\Mpp;

use App\Exceptions\MppPlanRuleViolationException;
use App\Models\MasterAuditLog;
use App\Models\MppSupplyPlan;
use App\Models\User;
use App\Services\ManPowerPlanningService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalur baca dan ubah rencana pasokan talenta MPP (DOMAIN.md §8).
 * Setiap perubahan diterapkan dalam satu transaksi dan dicatat per sel pada master_audit_logs.
 */
class MppSupplyPlanService
{
    private const REASON_MIN_LENGTH = 10;

    /**
     * Dua simpanan pertama untuk tahun yang sama hanya memegang gap lock di InnoDB dan dapat berakhir
     * deadlock. Laravel mengulang transaksi yang deadlock; pada percobaan ulang baris pemenang sudah
     * terlihat sehingga pengaman perubahan admin lain menolak simpanan kedua dengan pesan yang aman.
     */
    private const TRANSACTION_ATTEMPTS = 3;

    /**
     * Rencana pasokan satu tahun, terisi nol untuk setiap sel (juga bila tahun belum direncanakan).
     *
     * @return array<string, array<string, array<string, int>>> sumber => RM => bidang => jumlah
     */
    public function matrixFor(int $year): array
    {
        $matrix = $this->emptyMatrix();

        MppSupplyPlan::where('year', $year)
            ->get(['source', 'rm_level', 'bidang', 'jumlah'])
            ->each(function (MppSupplyPlan $cell) use (&$matrix): void {
                if (isset($matrix[$cell->source][$cell->rm_level][$cell->bidang])) {
                    $matrix[$cell->source][$cell->rm_level][$cell->bidang] = $cell->jumlah;
                }
            });

        return $matrix;
    }

    public function hasPlan(int $year): bool
    {
        return MppSupplyPlan::where('year', $year)->exists();
    }

    /**
     * Siapa dan kapan rencana satu tahun terakhir diubah. Null bila tahun belum direncanakan;
     * 'by' null bila akun pengubah terakhir sudah tidak ada.
     *
     * @return array{year: int, by: ?string, at: ?Carbon}|null
     */
    public function provenance(int $year): ?array
    {
        if (! $this->hasPlan($year)) {
            return null;
        }

        $latest = MppSupplyPlan::where('year', $year)
            ->whereNotNull('updated_by')
            ->with('editor:id,name')
            ->latest('updated_at')
            ->first();

        return ['year' => $year, 'by' => $latest?->editor?->name, 'at' => $latest?->updated_at];
    }

    /**
     * Simpan rencana pasokan satu tahun. Seluruh 48 sel wajib dikirim; $expected adalah nilai yang
     * dilihat admin saat membuka editor, dipakai untuk menolak perubahan yang menimpa simpanan admin lain.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $cells
     * @param  array<string, array<string, array<string, mixed>>>  $expected
     * @return int jumlah baris riwayat yang dicatat (0 bila tidak ada perubahan)
     *
     * @throws MppPlanRuleViolationException
     */
    public function save(int $year, array $cells, array $expected, ?string $reason, User $user): int
    {
        if ($year < ManPowerPlanningService::PLANNING_YEAR_MIN || $year > ManPowerPlanningService::PLANNING_YEAR_MAX) {
            throw new MppPlanRuleViolationException('Tahun perencanaan harus antara '.ManPowerPlanningService::PLANNING_YEAR_MIN.' dan '.ManPowerPlanningService::PLANNING_YEAR_MAX.'.');
        }

        $cells = $this->validateCells($cells);
        $expected = $this->normalize($expected);

        $reason = trim((string) $reason);
        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new MppPlanRuleViolationException('Alasan perubahan wajib diisi minimal '.self::REASON_MIN_LENGTH.' karakter.');
        }

        return DB::transaction(function () use ($year, $cells, $expected, $reason, $user): int {
            $rows = MppSupplyPlan::where('year', $year)->lockForUpdate()->get()
                ->keyBy(fn (MppSupplyPlan $row): string => $this->cellKey($row->source, $row->rm_level, $row->bidang));
            $isNew = $rows->isEmpty();

            $stored = $this->emptyMatrix();
            foreach ($rows as $row) {
                if (isset($stored[$row->source][$row->rm_level][$row->bidang])) {
                    $stored[$row->source][$row->rm_level][$row->bidang] = $row->jumlah;
                }
            }

            if ($stored !== $expected) {
                throw new MppPlanRuleViolationException("Rencana pasokan {$year} telah diubah oleh pengguna lain. Muat ulang lalu ulangi perubahan.");
            }

            $action = $isNew ? 'mpp_supply.created' : 'mpp_supply.updated';
            $audited = 0;

            foreach ($cells as $source => $levels) {
                foreach ($levels as $rm => $fields) {
                    foreach ($fields as $bidang => $jumlah) {
                        $row = $rows->get($this->cellKey($source, $rm, $bidang));
                        $old = $row?->jumlah ?? 0;

                        if ($row === null) {
                            $row = MppSupplyPlan::create([
                                'year' => $year, 'source' => $source, 'rm_level' => $rm, 'bidang' => $bidang,
                                'jumlah' => $jumlah, 'updated_by' => $user->id,
                            ]);
                        } elseif ($old !== $jumlah) {
                            $row->update(['jumlah' => $jumlah, 'updated_by' => $user->id]);
                        }

                        if ($old !== $jumlah) {
                            MasterAuditLog::record(
                                $user, $action, 'mpp_supply', $row->id, $this->cellLabel($year, $source, $rm, $bidang),
                                $isNew ? null : ['jumlah' => $old], ['jumlah' => $jumlah], $reason
                            );
                            $audited++;
                        }
                    }
                }
            }

            // Rencana baru yang seluruhnya nol tetap merupakan keputusan yang dicatat.
            if ($isNew && $audited === 0) {
                MasterAuditLog::record($user, $action, 'mpp_supply', null, "Rencana Pasokan {$year} — seluruh sel", null, ['jumlah' => 0], $reason);
                $audited = 1;
            }

            return $audited;
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Validasi isian rencana tanpa menyimpan: bentuk lengkap, bilangan bulat 0..999, dan jalur rekrutmen hanya
     * pada RM-3. Dipakai editor saat meninjau perubahan dan oleh save(), sehingga aturannya hanya ada di sini.
     *
     * @return array<string, array<string, array<string, int>>>
     *
     * @throws MppPlanRuleViolationException
     */
    public function validateCells(array $cells): array
    {
        $cells = $this->normalize($cells);
        $this->assertAllowedCells($cells);

        return $cells;
    }

    /**
     * Pastikan bentuk sel lengkap (sumber x RM x bidang), tanpa kunci asing, dengan bilangan bulat 0..999.
     *
     * @return array<string, array<string, array<string, int>>>
     */
    private function normalize(array $cells): array
    {
        $template = $this->emptyMatrix();

        if (array_keys($cells) != array_keys($template)) {
            throw new MppPlanRuleViolationException('Susunan rencana pasokan tidak dikenali. Muat ulang halaman lalu ulangi.');
        }

        foreach ($template as $source => $levels) {
            if (! is_array($cells[$source]) || array_keys($cells[$source]) != MppSupplyPlan::LEVELS) {
                throw new MppPlanRuleViolationException('Susunan rencana pasokan tidak dikenali. Muat ulang halaman lalu ulangi.');
            }

            foreach ($levels as $rm => $fields) {
                if (! is_array($cells[$source][$rm]) || array_keys($cells[$source][$rm]) != MppSupplyPlan::BIDANG) {
                    throw new MppPlanRuleViolationException('Susunan rencana pasokan tidak dikenali. Muat ulang halaman lalu ulangi.');
                }

                foreach (array_keys($fields) as $bidang) {
                    $value = $cells[$source][$rm][$bidang];
                    $isWhole = is_int($value) || (is_string($value) && ctype_digit($value));

                    if (! $isWhole || (int) $value < 0 || (int) $value > MppSupplyPlan::MAX_JUMLAH) {
                        throw new MppPlanRuleViolationException(
                            MppSupplyPlan::SOURCES[$source]." {$rm} {$bidang} harus berupa bilangan bulat 0 sampai ".MppSupplyPlan::MAX_JUMLAH.'.'
                        );
                    }

                    $template[$source][$rm][$bidang] = (int) $value;
                }
            }
        }

        return $template;
    }

    /**
     * Jalur rekrutmen hanya memasok formasi RM-3; sel RM-1/RM-2 sumber tersebut harus 0.
     */
    private function assertAllowedCells(array $cells): void
    {
        foreach ($cells as $source => $levels) {
            foreach ($levels as $rm => $fields) {
                foreach ($fields as $bidang => $jumlah) {
                    if ($jumlah !== 0 && ! MppSupplyPlan::allowsCell($source, $rm)) {
                        $label = MppSupplyPlan::SOURCES[$source];
                        throw new MppPlanRuleViolationException(
                            "{$label} hanya memasok formasi ".implode(', ', MppSupplyPlan::SUPPLY_LEVELS).". Kosongkan {$label} {$rm} {$bidang}."
                        );
                    }
                }
            }
        }
    }

    private function cellKey(string $source, string $rm, string $bidang): string
    {
        return "{$source}|{$rm}|{$bidang}";
    }

    private function cellLabel(int $year, string $source, string $rm, string $bidang): string
    {
        return "Rencana Pasokan {$year} — ".MppSupplyPlan::SOURCES[$source]." {$rm} {$bidang}";
    }

    /**
     * @return array<string, array<string, array<string, int>>>
     */
    private function emptyMatrix(): array
    {
        $row = array_fill_keys(MppSupplyPlan::BIDANG, 0);

        return array_fill_keys(array_keys(MppSupplyPlan::SOURCES), array_fill_keys(MppSupplyPlan::LEVELS, $row));
    }
}
