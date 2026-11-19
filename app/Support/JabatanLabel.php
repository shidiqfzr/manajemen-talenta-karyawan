<?php

namespace App\Support;

/**
 * Satu-satunya aturan penulisan "dan" menjadi "&" pada nama jabatan, mengikuti gaya penulisan SK (nama unit sudah memakai "&").
 * Dipakai `FormasiNormalizer::jabatan()` untuk membangun baseline master dan oleh katalog template impor, sehingga
 * daftar jabatan tampil seragam sementara master basis data yang berjalan belum disesuaikan. Nama jabatan pada basis data
 * yang berjalan, riwayat, dan pencocokan impor tidak diubah di sini (PositionNameMatcher memperlakukan "dan" dan "&"
 * setara); perubahan nama pada master berjalan hanya melalui SK (DOMAIN.md §3.7).
 */
final class JabatanLabel
{
    /** Contoh: "Asisten Tata Usaha dan Keuangan" menjadi "Asisten Tata Usaha & Keuangan"; koma sebelum "dan" ikut dibuang. */
    public static function display(string $jabatan): string
    {
        return (string) preg_replace('/,? dan /', ' & ', $jabatan);
    }
}
