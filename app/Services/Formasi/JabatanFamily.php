<?php

namespace App\Services\Formasi;

/**
 * Menurunkan "rumpun" jabatan dari nama jabatan yang dinomori per afdeling/bagian, agar tampilan Sebaran Formasi
 * (DOMAIN.md §9.3) menyatukan "Asisten Afdeling I" s.d. "Asisten Afdeling XIII" menjadi satu baris.
 *
 * Aturan: angka romawi di akhir nama dibuang (termasuk sufiks huruf "IIA" dan pasangan "I dan II" / "VII & VIII").
 * Singkatan seperti IT, QC, SDM dan nama tempat tidak diubah, dan nama tidak pernah dipangkas sampai kosong.
 */
final class JabatanFamily
{
    /** Angka romawi I s.d. XXXIX, sufiks huruf A atau B opsional ("IIA"). */
    private const NUMERAL = '/^(?=[IVX])X{0,3}(?:IX|IV|V?I{0,3})[AB]?$/';

    public static function of(string $jabatan): string
    {
        $words = preg_split('/\s+/u', trim($jabatan), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        while (count($words) > 1 && self::isNumeral(end($words))) {
            $count = count($words);
            $isPair = $count > 3
                && in_array($words[$count - 2], ['dan', '&'], true)
                && self::isNumeral($words[$count - 3]);

            array_splice($words, $isPair ? -3 : -1);
        }

        return implode(' ', $words);
    }

    private static function isNumeral(string $word): bool
    {
        return preg_match(self::NUMERAL, $word) === 1;
    }
}
