<?php

namespace App\Services\Formasi;

/**
 * Hasil pratinjau dampak sebuah perubahan master formasi.
 * Dipakai panel dampak di layar dan pemeriksaan sisi server yang sama.
 */
final readonly class ImpactResult
{
    /**
     * @param  list<string>  $blockers  Alasan perubahan ditolak
     * @param  list<string>  $warnings  Peringatan yang tetap mengizinkan perubahan
     */
    public function __construct(
        public bool $blocked = false,
        public array $blockers = [],
        public array $warnings = [],
        public int $affectedEmployees = 0,
    ) {}

    /** @param list<string> $warnings */
    public static function ok(array $warnings = [], int $affected = 0): self
    {
        return new self(false, [], $warnings, $affected);
    }

    /** @param list<string> $blockers */
    public static function blocked(array $blockers, int $affected = 0): self
    {
        return new self(true, $blockers, [], $affected);
    }
}
