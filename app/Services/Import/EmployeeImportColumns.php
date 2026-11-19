<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Satu-satunya definisi kolom spreadsheet karyawan (DRY): judul kolom, tanda wajib, jenis format, nama alternatif pada baris
 * mentah, label rincian perubahan, dan urutan kolom per tata letak. Template impor, ekspor data, berkas baris gagal, kamus
 * format, dan pengimpor semua membaca daftar ini, sehingga menambah atau mengubah kolom cukup di sini.
 *
 * Dua tata letak:
 * - TEMPLATE (24 kolom, A–X): Unit Kerja mendahului Jabatan karena dropdown Jabatan bergantung pada unit. Strata (Karpim/Karpel)
 *   dan RM Band tidak diminta karena diturunkan dari Formasi Jabatan pada Master Formasi.
 * - TABULAR (26 kolom, A–Z): ekspor data karyawan dan berkas baris gagal; memuat Level dan RM Level.
 */
final class EmployeeImportColumns
{
    public const LAYOUT_TEMPLATE = 'template';

    public const LAYOUT_TABULAR = 'tabular';

    public const TYPE_TEXT = 'text';

    public const TYPE_DATE = 'date';

    /**
     * Definisi per bidang: heading (huruf besar, tanpa tanda wajib), required, type, aliases (nama alternatif pada baris
     * mentah berkas lama, setelah dinormalkan), label (rincian perubahan pada pratinjau).
     *
     * @var array<string, array{heading: string, required: bool, type: string, aliases: list<string>, label: string}>
     */
    private const FIELDS = [
        'nik' => ['heading' => 'NIK', 'required' => true, 'type' => self::TYPE_TEXT, 'aliases' => [], 'label' => 'NIK'],
        'nama' => ['heading' => 'NAMA', 'required' => true, 'type' => self::TYPE_TEXT, 'aliases' => [], 'label' => 'Nama Lengkap'],
        'jenis_kelamin' => ['heading' => 'JENIS KELAMIN', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => ['jk'], 'label' => 'Jenis Kelamin'],
        'tempat_lahir' => ['heading' => 'TEMPAT LAHIR', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => [], 'label' => 'Tempat Lahir'],
        'tanggal_lahir' => ['heading' => 'TANGGAL LAHIR', 'required' => false, 'type' => self::TYPE_DATE, 'aliases' => ['tgl_lahir'], 'label' => 'Tanggal Lahir'],
        'agama' => ['heading' => 'AGAMA', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => [], 'label' => 'Agama'],
        'susunan_keluarga' => ['heading' => 'SUSUNAN KELUARGA', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => ['ptkp'], 'label' => 'Susunan Keluarga / PTKP'],
        'status_karyawan' => ['heading' => 'STATUS KARYAWAN', 'required' => true, 'type' => self::TYPE_TEXT, 'aliases' => [], 'label' => 'Status Karyawan'],
        'unit_kerja' => ['heading' => 'UNIT KERJA', 'required' => true, 'type' => self::TYPE_TEXT, 'aliases' => ['unit'], 'label' => 'Unit Kerja'],
        'jabatan' => ['heading' => 'JABATAN', 'required' => true, 'type' => self::TYPE_TEXT, 'aliases' => [], 'label' => 'Jabatan'],
        'level' => ['heading' => 'LEVEL', 'required' => true, 'type' => self::TYPE_TEXT, 'aliases' => ['strata'], 'label' => 'Strata (Level)'],
        'golongan' => ['heading' => 'GOLONGAN', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => ['gol'], 'label' => 'Golongan'],
        'rm_level' => ['heading' => 'RM LEVEL', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => ['rm'], 'label' => 'RM Level'],
        'status_penugasan' => ['heading' => 'STATUS JABATAN UTAMA', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => ['status_jabatan_utama'], 'label' => 'Status Jabatan Utama'],
        'tugas_rangkap_penugasan_sementara' => ['heading' => 'TUGAS RANGKAP / PENUGASAN SEMENTARA', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => ['tugas_rangkap'], 'label' => 'Tugas Rangkap'],
        'tmt_bekerja' => ['heading' => 'TMT BEKERJA', 'required' => false, 'type' => self::TYPE_DATE, 'aliases' => [], 'label' => 'TMT Bekerja'],
        'tanggal_diangkat_staf' => ['heading' => 'TANGGAL DIANGKAT STAF', 'required' => false, 'type' => self::TYPE_DATE, 'aliases' => ['tgl_staf'], 'label' => 'Tanggal Diangkat Staf'],
        'tanggal_dalam_jabatan' => ['heading' => 'TANGGAL DALAM JABATAN', 'required' => false, 'type' => self::TYPE_DATE, 'aliases' => ['tgl_jabatan'], 'label' => 'Tanggal Dalam Jabatan'],
        'tmt_unit_kerja' => ['heading' => 'TMT UNIT KERJA', 'required' => false, 'type' => self::TYPE_DATE, 'aliases' => ['tmt_unit'], 'label' => 'TMT Unit Kerja'],
        'jalur_masuk' => ['heading' => 'JALUR MASUK', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => [], 'label' => 'Jalur Masuk'],
        'job_grade' => ['heading' => 'JOB GRADE', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => [], 'label' => 'Job Grade'],
        'person_grade' => ['heading' => 'PERSON GRADE', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => [], 'label' => 'Person Grade'],
        'pendidikan_terakhir' => ['heading' => 'PENDIDIKAN TERAKHIR', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => ['pendidikan'], 'label' => 'Pendidikan Terakhir'],
        'sekolah' => ['heading' => 'SEKOLAH', 'required' => false, 'type' => self::TYPE_TEXT, 'aliases' => ['universitas'], 'label' => 'Sekolah / Universitas'],
        'tanggal_mbt' => ['heading' => 'TANGGAL MBT', 'required' => false, 'type' => self::TYPE_DATE, 'aliases' => ['mbt'], 'label' => 'Tanggal MBT'],
        'tanggal_pensiun' => ['heading' => 'TANGGAL PENSIUN', 'required' => false, 'type' => self::TYPE_DATE, 'aliases' => ['pensiun'], 'label' => 'Tanggal Pensiun'],
    ];

    /** @var array<string, list<string>> urutan bidang per tata letak (kelompok: identitas, jabatan & formasi, masa kerja & karir, pendidikan & pensiun) */
    private const LAYOUTS = [
        self::LAYOUT_TEMPLATE => [
            // Identitas Utama (Kolom A - G)
            'nik', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'susunan_keluarga',
            // Info Jabatan & Formasi (Kolom H - M)
            'status_karyawan', 'unit_kerja', 'jabatan', 'golongan', 'status_penugasan', 'tugas_rangkap_penugasan_sementara',
            // Masa Kerja & Karir (Kolom N - T)
            'tmt_bekerja', 'tanggal_diangkat_staf', 'tanggal_dalam_jabatan', 'tmt_unit_kerja', 'jalur_masuk', 'job_grade', 'person_grade',
            // Lain-lain / Pendidikan & Pensiun (Kolom U - X)
            'pendidikan_terakhir', 'sekolah', 'tanggal_mbt', 'tanggal_pensiun',
        ],
        self::LAYOUT_TABULAR => [
            // Identitas Utama (Kolom A - G)
            'nik', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'susunan_keluarga',
            // Info Jabatan & Formasi (Kolom H - O)
            'status_karyawan', 'jabatan', 'level', 'unit_kerja', 'golongan', 'rm_level', 'status_penugasan', 'tugas_rangkap_penugasan_sementara',
            // Masa Kerja & Karir (Kolom P - V)
            'tmt_bekerja', 'tanggal_diangkat_staf', 'tanggal_dalam_jabatan', 'tmt_unit_kerja', 'jalur_masuk', 'job_grade', 'person_grade',
            // Lain-lain / Pendidikan & Pensiun (Kolom W - Z)
            'pendidikan_terakhir', 'sekolah', 'tanggal_mbt', 'tanggal_pensiun',
        ],
    ];

    /** @var array<string, string>|null kunci judul ternormalisasi => kunci bidang */
    private static ?array $fieldByHeadingKey = null;

    /**
     * @return list<string> kunci bidang menurut urutan tata letak
     */
    public static function fields(string $layout = self::LAYOUT_TEMPLATE): array
    {
        return self::LAYOUTS[$layout];
    }

    /** Bidang wajib pada tata letak itu, menurut urutannya. @return list<string> */
    public static function requiredFields(string $layout = self::LAYOUT_TEMPLATE): array
    {
        return array_values(array_filter(self::fields($layout), fn (string $field): bool => self::FIELDS[$field]['required']));
    }

    /** Bidang dengan jenis format tertentu (TYPE_TEXT atau TYPE_DATE). @return list<string> */
    public static function fieldsOfType(string $type, string $layout = self::LAYOUT_TEMPLATE): array
    {
        return array_values(array_filter(self::fields($layout), fn (string $field): bool => self::FIELDS[$field]['type'] === $type));
    }

    /** Judul kolom; $marked menambahkan tanda bintang pada kolom wajib (template dan berkas baris gagal). */
    public static function heading(string $field, bool $marked = false): string
    {
        $definition = self::FIELDS[$field];

        return $definition['heading'].($marked && $definition['required'] ? '*' : '');
    }

    /** @return list<string> judul seluruh kolom tata letak itu menurut urutannya */
    public static function headings(string $layout = self::LAYOUT_TEMPLATE, bool $marked = false): array
    {
        return array_map(fn (string $field): string => self::heading($field, $marked), self::fields($layout));
    }

    /** Huruf kolom Excel (A, B, ...) untuk bidang pada tata letak itu. */
    public static function letter(string $field, string $layout = self::LAYOUT_TEMPLATE): string
    {
        $position = array_search($field, self::fields($layout), true);

        if ($position === false) {
            throw new \InvalidArgumentException("Bidang '{$field}' tidak ada pada tata letak '{$layout}'.");
        }

        return Coordinate::stringFromColumnIndex($position + 1);
    }

    /** Huruf kolom terakhir tata letak itu (X untuk template, Z untuk tabular). */
    public static function lastLetter(string $layout = self::LAYOUT_TEMPLATE): string
    {
        return Coordinate::stringFromColumnIndex(count(self::fields($layout)));
    }

    /** @return list<string> huruf seluruh kolom, dari A sampai kolom terakhir */
    public static function letters(string $layout = self::LAYOUT_TEMPLATE): array
    {
        return array_map(fn (int $index): string => Coordinate::stringFromColumnIndex($index), range(1, count(self::fields($layout))));
    }

    /** Label bidang pada rincian perubahan pratinjau. */
    public static function label(string $field): string
    {
        return self::FIELDS[$field]['label'];
    }

    /** Kunci pencarian nilai pada baris mentah: nama bidang lalu nama alternatifnya. @return list<string> */
    public static function rawKeys(string $field): array
    {
        return [$field, ...self::FIELDS[$field]['aliases']];
    }

    /** Judul kolom dalam bentuk kunci ternormalisasi (huruf kecil, garis bawah), mis. "STATUS JABATAN UTAMA" menjadi status_jabatan_utama. */
    public static function headingKey(string $field): string
    {
        return self::normaliseHeading(self::FIELDS[$field]['heading']);
    }

    /** Satu aturan normalisasi judul kolom spreadsheet: tanda bintang dibuang, selain huruf dan angka menjadi garis bawah. */
    public static function normaliseHeading(string $heading): string
    {
        $clean = str_replace('*', '', $heading);

        return strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', trim($clean)), '_'));
    }

    /**
     * Kunci bidang dari judul kolom yang sudah dinormalkan: judul terbaru "Status Jabatan Utama" dan judul lama
     * "Status Penugasan" sama-sama menjadi status_penugasan; judul lain dikembalikan apa adanya.
     */
    public static function fieldForHeadingKey(string $headingKey): string
    {
        // Dipanggil untuk setiap sel judul pada setiap baris: petanya dibangun sekali.
        self::$fieldByHeadingKey ??= array_combine(array_map(self::headingKey(...), array_keys(self::FIELDS)), array_keys(self::FIELDS));

        return self::$fieldByHeadingKey[$headingKey] ?? $headingKey;
    }
}
