<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    /** Batas atas biaya (kolom integer 32-bit pada basis data). */
    public const BIAYA_MAX = 2147483647;

    /** Batas atas jam belajar per hari. */
    public const JAM_BELAJAR_MAX = 24;

    /** Jenis pelatihan baku (DOMAIN.md §2.4). */
    public const JENIS_LIST = [
        'Leadership',
        'Teknis Kelapa Sawit',
        'Teknis Pabrik',
        'Finance/Akuntansi',
        'K3 & Sustainability',
    ];

    /** Metode pelatihan baku (DOMAIN.md §2.4). */
    public const METODE_LIST = [
        'Klasikal (Tatap Muka)',
        'In-House Training',
        'E-Learning',
        'Workshop Praktik',
    ];

    /**
     * Pilihan Jenis untuk formulir. Nilai lama yang tersimpan sebelum daftar baku diberlakukan tetap
     * ditampilkan agar data historis tidak hilang saat pelatihan diubah.
     *
     * @return list<string>
     */
    public static function jenisOptions(?string $current = null): array
    {
        return self::withLegacy(self::JENIS_LIST, $current);
    }

    /**
     * @return list<string>
     */
    public static function metodeOptions(?string $current = null): array
    {
        return self::withLegacy(self::METODE_LIST, $current);
    }

    /**
     * Pilihan Bidang (kode => label) dari daftar bidang fungsional baku, ditambah nilai lama bila ada.
     *
     * @return array<string, string>
     */
    public static function bidangOptions(?string $current = null): array
    {
        $options = Position::BIDANG_LIST;

        if ($current !== null && $current !== '' && ! array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }

    /**
     * @param  list<string>  $list
     * @return list<string>
     */
    private static function withLegacy(array $list, ?string $current): array
    {
        if ($current !== null && $current !== '' && ! in_array($current, $list, true)) {
            $list[] = $current;
        }

        return $list;
    }

    protected $fillable = [
        'judul',
        'jenis',
        'metode',
        'tanggal_mulai',
        'tanggal_akhir',
        'biaya_tiket_peserta',
        'biaya_pelatihan',
        'penyelenggara',
        'nomor_surat',
        'tanggal_surat',
        'keterangan',
        'bidang_pelatihan',
        'jam_belajar_per_hari',
        'jumlah_man_hours',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_akhir' => 'date',
    ];

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_training', 'training_id', 'employee_nik')
            ->withPivot(['sertifikat'])
            ->withTimestamps();
    }
}
