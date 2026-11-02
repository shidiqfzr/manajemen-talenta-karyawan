<?php

namespace App\Services\Data;

use Carbon\Carbon;

/**
 * Immutable input of one mutation / placement (DOMAIN.md §5.2 & §5.4).
 *
 * Replaces the 17 positional parameters that used to be threaded through
 * MutasiService::processMutasi → doTransfer / doSwap → recordJobHistory.
 * Blank strings are normalised to null so persistence code never re-checks them.
 */
final readonly class MutasiRequest
{
    public ?string $nomorSk;

    public ?string $tanggalSk;

    public ?string $catatan;

    public ?string $tanggalBerakhirSanksi;

    public ?string $tmtAkhirEstimasi;

    public ?string $fileSk;

    public ?string $alasanDispensasi;

    public ?string $newGolongan;

    public ?string $newJobGrade;

    public function __construct(
        public string $employeeNik,
        public string $targetJabatan,
        public string $targetUnitKerja,
        public string $tmtAwal,
        public string $jenisMutasi = 'MUTASI_UNIT',
        ?string $nomorSk = null,
        ?string $tanggalSk = null,
        ?string $catatan = null,
        ?string $tanggalBerakhirSanksi = null,
        public string $statusJabatan = 'Definitif',
        ?string $tmtAkhirEstimasi = null,
        ?string $fileSk = null,
        public ?int $createdBy = null,
        public bool $isDispensasi = false,
        ?string $alasanDispensasi = null,
        ?string $newGolongan = null,
        ?string $newJobGrade = null,
    ) {
        $this->nomorSk = self::blankToNull($nomorSk);
        $this->tanggalSk = self::blankToNull($tanggalSk);
        $this->catatan = self::blankToNull($catatan);
        $this->tanggalBerakhirSanksi = self::blankToNull($tanggalBerakhirSanksi);
        $this->tmtAkhirEstimasi = self::blankToNull($tmtAkhirEstimasi);
        $this->fileSk = self::blankToNull($fileSk);
        $this->alasanDispensasi = self::blankToNull($alasanDispensasi);
        $this->newGolongan = self::blankToNull($newGolongan);
        $this->newJobGrade = self::blankToNull($newJobGrade);
    }

    /**
     * Build the request of one participant of a collective SK (chain mutation).
     * Header carries the shared SK metadata & TMT; the node carries the per-employee choices.
     */
    public static function fromChainNode(array $header, array $node): self
    {
        $targetJabatan = (string) $node['target_jabatan'];
        $targetUnitKerja = (string) $node['target_unit_kerja'];

        $catatan = $node['catatan'] ?? null;
        if (blank($catatan) && filled($header['catatan'] ?? null)) {
            $catatan = '['.($header['nomor_sk'] ?? '').'] '.$header['catatan'];
        }

        return new self(
            employeeNik: (string) $node['employee_nik'],
            targetJabatan: $targetJabatan,
            targetUnitKerja: $targetUnitKerja,
            tmtAwal: (string) $header['tmt_awal'],
            jenisMutasi: $node['jenis_mutasi'] ?? 'MUTASI_UNIT',
            nomorSk: $header['nomor_sk'] ?? null,
            tanggalSk: $header['tanggal_sk'] ?? null,
            catatan: $catatan,
            statusJabatan: $node['status_jabatan'] ?? 'Definitif',
            tmtAkhirEstimasi: $node['tmt_akhir_estimasi'] ?? null,
            fileSk: $header['file_sk'] ?? null,
            createdBy: $header['created_by'] ?? auth()->id(),
            isDispensasi: ! empty($node['is_dispensasi']),
            alasanDispensasi: $node['alasan_dispensasi'] ?? null,
            newGolongan: $node['new_golongan'] ?? null,
            newJobGrade: isset($node['new_job_grade']) ? (string) $node['new_job_grade'] : null,
        );
    }

    /** TMT lies after today → the mutation is staged (is_applied = false) instead of applied. */
    public function isFuture(): bool
    {
        return Carbon::parse($this->tmtAwal)->startOfDay()->isAfter(Carbon::today());
    }

    public function isDefinitif(): bool
    {
        return $this->statusJabatan === 'Definitif';
    }

    /** The previous definitive job ends the day before the new TMT. */
    public function tmtAkhir(): string
    {
        return Carbon::parse($this->tmtAwal)->subDay()->toDateString();
    }

    /** A dispensation only counts when a written reason accompanies it. */
    public function hasDispensasi(): bool
    {
        return $this->isDispensasi && $this->alasanDispensasi !== null;
    }

    public function defaultCatatan(): string
    {
        return "Mutasi ke {$this->targetJabatan} di {$this->targetUnitKerja}";
    }

    private static function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
