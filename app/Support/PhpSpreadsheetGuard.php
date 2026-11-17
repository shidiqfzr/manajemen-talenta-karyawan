<?php

namespace App\Support;

/**
 * Menjalankan pembacaan atau penulisan Excel tanpa membanjiri penangan galat dengan notis "deprecated" dari pustaka.
 *
 * PhpSpreadsheet versi terpasang memicu ribuan notis deprecated per berkas pada PHP 8.5 (kenaikan nilai string
 * kolom). Setiap notis melewati penangan galat aplikasi; pada permintaan web hal itu membuat satu unggahan
 * memakan puluhan detik. Penjaga ini hanya membuang notis deprecated selama $callback berjalan; galat, peringatan,
 * dan pengecualian tetap sampai ke penangan biasa. Penyelesaian akarnya adalah memperbarui pustaka.
 */
final class PhpSpreadsheetGuard
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(callable $callback): mixed
    {
        set_error_handler(static fn (): bool => true, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
