<?php

namespace App\Services\Mutasi;

/**
 * Thresholds of the HR governance for mutations (DOMAIN.md §5.1 & §5.2).
 *
 * Kept in one place so a change of the Pedoman Tata Kelola SDM is a one-line change
 * instead of a hunt through the services.
 */
final class MutasiPolicy
{
    /** Minimal tenure in the current unit before a unit rotation is allowed (2 years). */
    public const MIN_UNIT_TENURE_MONTHS = 24;

    /** Minimal number of employees of a collective SK (chain mutation). */
    public const MIN_CHAIN_PARTICIPANTS = 2;

    /** Retirement countdown (years) from which an employee is flagged as "approaching retirement". */
    public const RETIREMENT_WARNING_YEARS = 2;

    /** RM-3 → RM-2: minimal golongan OR assistant tenure, plus a "Baik" evaluation. */
    public const RM3_TO_RM2_GOLONGAN = ['IIIC', 'IIID', 'IVA', 'IVB', 'IVC', 'IVD'];

    public const RM3_TO_RM2_MIN_TENURE_YEARS = 4.0;

    /** RM-2 → RM-1: minimal golongan AND tenure as Askep/Kasubag. */
    public const RM2_TO_RM1_GOLONGAN = ['IIID', 'IVA', 'IVB', 'IVC', 'IVD'];

    public const RM2_TO_RM1_MIN_TENURE_YEARS = 3.0;

    /** Evaluation counted as "Baik": SMKBK score or a qualifying 9-box quadrant. */
    public const GOOD_EVALUATION_MIN_SCORE = 75;

    public const GOOD_EVALUATION_BOXES = ['Box 5', 'Box 6', 'Box 8', 'Box 9'];

    public static function minUnitTenureYears(): int
    {
        return intdiv(self::MIN_UNIT_TENURE_MONTHS, 12);
    }
}
