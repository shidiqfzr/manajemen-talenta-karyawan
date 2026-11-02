<?php

namespace App\Services\Formasi;

/**
 * Menyeragamkan penulisan nama unit dan jabatan dari berkas formasi (ALL CAPS)
 * ke gaya penulisan master data yang sudah berlaku.
 */
class FormasiNormalizer
{
    /** Salah ketik yang diketahui pada berkas formasi (huruf besar semua). */
    private const TYPOS = [
        'AKUNTASI' => 'AKUNTANSI',
        'ADMINISTASI' => 'ADMINISTRASI',
        'REKRUITMEN' => 'REKRUTMEN',
        'PERIJINAN' => 'PERIZINAN',
        'GROUP MANAJER' => 'GROUP MANAGER',
    ];

    /** Singkatan yang tetap ditulis huruf besar. */
    private const ACRONYMS = [
        'PKS', 'PKR', 'PMO', 'ERP', 'SAP', 'TI', 'TJSL', 'UMK', 'HPS', 'URTA', 'KKPA', 'T3M', 'PSDM', 'SDM',
        'QC', 'ESG', 'HI', 'CO', 'FI', 'HCM', 'MM', 'PM', 'SD', 'PP', 'QM', 'FM', 'PS', 'BKM', 'SPB',
    ];

    /** Kata sambung yang ditulis huruf kecil. */
    private const LOWERCASE_WORDS = ['DAN' => 'dan', 'OF' => 'of'];

    /** Angka romawi I sampai XXXIX, dengan akhiran huruf A/B (mis. IIA). */
    private const ROMAN_PATTERN = '/^(?=[IVXL]+[AB]?$)X{0,3}(?:IX|IV|V?I{0,3})[AB]?$/';

    public static function unit(string $raw): string
    {
        // Nama unit memakai '&' (bukan 'dan'), mengikuti penulisan master unit.
        return str_replace(' dan ', ' & ', self::format($raw));
    }

    public static function jabatan(string $raw): string
    {
        return self::format($raw);
    }

    private static function format(string $raw): string
    {
        $text = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $raw) ?? ''));
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        foreach (self::TYPOS as $wrong => $right) {
            $text = preg_replace('/\b'.preg_quote($wrong, '/').'\b/u', $right, $text) ?? $text;
        }

        return preg_replace_callback('/[A-Z0-9]+/u', function (array $match): string {
            $word = $match[0];

            if (in_array($word, self::ACRONYMS, true) || preg_match(self::ROMAN_PATTERN, $word)) {
                return $word;
            }

            if (isset(self::LOWERCASE_WORDS[$word])) {
                return self::LOWERCASE_WORDS[$word];
            }

            return ucfirst(strtolower($word));
        }, $text) ?? $text;
    }
}
