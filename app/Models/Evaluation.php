<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;

    /** Batas atas skala nilai per kriteria dan hasil skor asesmen (0 sampai NILAI_MAX). */
    public const NILAI_MAX = 10;

    /** Batas atas skor 9-box SMK-BK dan CLI (0 sampai SKOR_MAX). */
    public const SKOR_MAX = 100;

    /**
     * Sembilan kuadran matriks talenta (DOMAIN.md §6), diberi nomor 1 sampai 9 dari kiri-bawah ke kanan-atas:
     * baris naik menurut potensi (CLI), kolom naik menurut kinerja (SMK-BK).
     */
    public const BOX_LABELS = [
        1 => 'Risk',
        2 => 'Effective',
        3 => 'Specialist',
        4 => 'Dilemma',
        5 => 'Core Talent',
        6 => 'High Performer',
        7 => 'Diamond',
        8 => 'High Potential',
        9 => 'Star',
    ];

    /** Tingkat pada sumbu matriks: indeks 0 sampai 2. */
    private const BOX_LEVELS = ['Rendah', 'Sedang', 'Tinggi'];

    /**
     * Nilai tersimpan satu kuadran, mis. "Box 9: Star". Format ini dibaca aturan mutasi
     * (MutasiPolicy::GOOD_EVALUATION_BOXES), sehingga tidak boleh berubah tanpa mengubah aturan itu.
     */
    public static function boxValue(int $number): string
    {
        return 'Box '.$number.': '.self::BOX_LABELS[$number];
    }

    /**
     * Pilihan Kategori 9-Box untuk formulir (nilai tersimpan => teks tampil dengan arti kuadran).
     * Nilai lama yang tersimpan sebelum daftar baku diberlakukan tetap ditawarkan agar tidak hilang saat diubah.
     *
     * @return array<string, string>
     */
    public static function boxOptions(?string $current = null): array
    {
        $options = [];

        foreach (array_keys(self::BOX_LABELS) as $number) {
            $options[self::boxValue($number)] = self::boxValue($number).' ('.self::boxMeaning($number).')';
        }

        if ($current !== null && $current !== '' && ! array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }

    /**
     * Arti kuadran dari posisinya pada matriks, mis. "Potensi Tinggi, Kinerja Tinggi" untuk Box 9.
     */
    public static function boxMeaning(int $number): string
    {
        $index = $number - 1;

        return 'Potensi '.self::BOX_LEVELS[intdiv($index, 3)].', Kinerja '.self::BOX_LEVELS[$index % 3];
    }

    protected $fillable = [
        'employee_nik',
        'nilai_kepemimpinan',
        'nilai_perilaku_budaya',
        'nilai_pengalaman_teknis',
        'nilai_kematangan_pribadi',
        'nilai_tertimbang',
        'skor_smkbk_9box',
        'skor_cli_9box',
        'kategori_9box',
        'bidang_tugas',
        'lembaga_asesmen',
        'tanggal_pelaksanaan_asesmen',
        'hasil_skor_asesmen',
        'kategori_asesmen',
        'keterangan_asesmen',
        'expired_asesmen',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_diangkat_staf' => 'date',
        'tanggal_pelaksanaan_asesmen' => 'date',
        'expired_asesmen' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }
}
