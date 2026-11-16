<?php

namespace App\Services\Mutasi;

use Carbon\Carbon;
use Throwable;

/**
 * Evidence of a Direksi dispensation (DOMAIN.md §5.4 & SECURITY.md audit trail).
 *
 * A dispensation is only valid with a document reference (nota dinas / SK persetujuan Direksi) and the date Direksi
 * approved it. Single and chain mutations share this rule so the UI, the form validation and the audit text agree.
 */
final class DispensasiBasis
{
    public const MIN_REFERENCE_LENGTH = 5;

    public const MAX_REFERENCE_LENGTH = 150;

    /** Reference is long enough and the approval date is a real date that is not in the future. */
    public static function isComplete(?string $reference, ?string $date): bool
    {
        return self::hasReference($reference) && self::parseDate($date) !== null;
    }

    public static function hasReference(?string $reference): bool
    {
        return mb_strlen(trim((string) $reference)) >= self::MIN_REFERENCE_LENGTH;
    }

    public static function hasValidDate(?string $date): bool
    {
        return self::parseDate($date) !== null;
    }

    /**
     * Audit text stored in job_histories.alasan_dispensasi, e.g. "Nota Dinas No. 12/BOD (disetujui 09/11/2026)".
     * Empty when the evidence is incomplete, so it can never be mistaken for an approval.
     */
    public static function compose(?string $reference, ?string $date): string
    {
        $approvedOn = self::parseDate($date);

        if (! self::hasReference($reference) || $approvedOn === null) {
            return '';
        }

        return trim((string) $reference).' (disetujui '.$approvedOn->format('d/m/Y').')';
    }

    private static function parseDate(?string $date): ?Carbon
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date);
        } catch (Throwable) {
            return null;
        }

        // Reject overflowing dates (2026-02-31) and approvals dated in the future
        if ($parsed === false || $parsed->format('Y-m-d') !== $date || $parsed->startOfDay()->isAfter(Carbon::today())) {
            return null;
        }

        return $parsed;
    }
}
