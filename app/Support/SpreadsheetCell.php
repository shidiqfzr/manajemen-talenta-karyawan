<?php

namespace App\Support;

/**
 * Satu-satunya penjaga injeksi formula (CWE-1236) untuk seluruh ekspor Excel (SECURITY.md, OWASP).
 *
 * Sel yang diawali =, +, -, @, tab, atau carriage return dapat dieksekusi sebagai rumus oleh aplikasi spreadsheet,
 * sehingga diberi awalan tanda kutip. Semua exporter wajib memakai kelas ini; dilarang menulis ulang aturannya.
 */
final class SpreadsheetCell
{
    /** Karakter pembuka yang membuat sel dibaca sebagai rumus. */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function neutralise(mixed $value): string
    {
        $text = (string) ($value ?? '');

        if ($text !== '' && in_array($text[0], self::FORMULA_PREFIXES, true)) {
            return "'".$text;
        }

        return $text;
    }
}
