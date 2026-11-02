<?php

namespace App\Services\Import;

/**
 * Pencocokan nama jabatan dari spreadsheet terhadap nama formasi jabatan resmi: persis, tanpa
 * membedakan huruf besar/kecil, lalu toleran terhadap tanda baca, '&' versus 'dan', dan spasi ganda.
 * Satu-satunya implementasi, dipakai validator baris dan parser tugas rangkap.
 */
final class PositionNameMatcher
{
    /**
     * @param  list<string>  $validPositions
     */
    public static function match(string $input, array $validPositions): ?string
    {
        if (in_array($input, $validPositions, true)) {
            return $input;
        }

        foreach ($validPositions as $pos) {
            if (strcasecmp($input, $pos) === 0) {
                return $pos;
            }
        }

        $cleanedInput = self::clean($input);
        foreach ($validPositions as $pos) {
            if (self::clean($pos) === $cleanedInput) {
                return $pos;
            }
        }

        return null;
    }

    private static function clean(string $str): string
    {
        $str = str_ireplace(['&', 'dan'], ' ', $str);
        $str = preg_replace('/\btanamanan\b/iu', 'tanaman', $str);
        $str = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $str);
        $str = preg_replace('/\s+/', ' ', trim($str));

        return mb_strtolower($str);
    }
}
