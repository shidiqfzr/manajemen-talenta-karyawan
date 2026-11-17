<?php

namespace App\Support;

/**
 * Satu aturan penyamaran NIK untuk seluruh layar dan ekspor (UU PDP; SECURITY.md): hanya tiga karakter terakhir yang
 * terlihat, dan nilai sependek tiga karakter atau kurang disamarkan penuh. Jangan menulis ulang aturan ini di tempat lain.
 */
final class NikMasker
{
    private const VISIBLE = 3;

    public static function mask(string $nik): string
    {
        $length = strlen($nik);

        if ($length <= self::VISIBLE) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - self::VISIBLE).substr($nik, -self::VISIBLE);
    }
}
