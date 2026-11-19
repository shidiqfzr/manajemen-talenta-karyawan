<?php

namespace App\Services\Import;

class EmployeeImportDictionary
{
    /**
     * Mengembalikan daftar 4 kategori beserta seluruh spesifikasi 26 kolom
     * sebagai Single Source of Truth (SSOT) format spreadsheet karyawan PTPN IV Regional V.
     */
    public static function getCategories(): array
    {
        return array_map(function (array $category): array {
            $letters = [];
            $category['columns'] = array_map(function (array $column) use (&$letters): array {
                // Huruf dan kunci kolom dari daftar kolom bersama: tidak ada salinan yang dapat tidak sinkron dengan template.
                $column['letter'] = EmployeeImportColumns::letter($column['field']);
                $column['key'] = EmployeeImportColumns::headingKey($column['field']);
                $letters[] = $column['letter'];

                return $column;
            }, $category['columns']);
            $category['title'] = str_replace('{range}', $letters[0].' – '.end($letters), $category['title']);
            $category['count_label'] = count($category['columns']).' Kolom';

            return $category;
        }, self::definitions());
    }

    /** Definisi kategori dan kolom (tanpa huruf, kunci, dan jumlah: ketiganya diturunkan dari EmployeeImportColumns). */
    private static function definitions(): array
    {
        return [
            [
                'id' => 'cat1',
                'title' => 'Kategori I • Identitas Karyawan & Pribadi (Kolom {range})',
                'icon' => 'fas fa-id-card',
                'columns' => [
                    [
                        'field' => 'nik',
                        'short_title' => null,
                        'is_mandatory' => true,
                        'type_label' => 'Teks (8 Digit)',
                        'type_sub' => 'Format Teks Baku',
                        'is_dropdown' => false,
                        'description' => '8 digit angka numerik unik. Auto-padding nol di depan jika terpotong Excel.',
                        'example' => '13009988',
                        'search_tags' => 'nomor induk unik 8 digit',
                    ],
                    [
                        'field' => 'nama',
                        'short_title' => null,
                        'is_mandatory' => true,
                        'type_label' => 'Teks Bebas',
                        'type_sub' => 'Nama Lengkap KTP',
                        'is_dropdown' => false,
                        'description' => 'Nama lengkap karyawan sesuai KTP. Otomatis distandarkan menjadi UPPERCASE oleh sistem.',
                        'example' => 'BUDI SANTOSO',
                        'search_tags' => 'nama lengkap karyawan uppercase ktp',
                    ],
                    [
                        'field' => 'jenis_kelamin',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Dropdown',
                        'type_sub' => 'Laki-laki / Perempuan',
                        'is_dropdown' => true,
                        'description' => 'Pilih dari dropdown: Laki-laki atau Perempuan.',
                        'example' => 'Laki-laki',
                        'search_tags' => 'gender jenis kelamin laki-laki perempuan dropdown',
                    ],
                    [
                        'field' => 'tempat_lahir',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Teks Bebas',
                        'type_sub' => 'Kota / Kabupaten',
                        'is_dropdown' => false,
                        'description' => 'Nama kota atau kabupaten tempat lahir sesuai KTP/Ijazah.',
                        'example' => 'Pontianak',
                        'search_tags' => 'kota kabupaten tempat lahir ktp',
                    ],
                    [
                        'field' => 'tanggal_lahir',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Tanggal (Date)',
                        'type_sub' => 'YYYY-MM-DD / DD/MM/YYYY',
                        'is_dropdown' => false,
                        'description' => 'Tanggal lahir karyawan. Digunakan untuk perhitungan otomatis Batas Usia Pensiun (56 thn) & MBT (6 bulan sebelum pensiun).',
                        'example' => '1985-05-15',
                        'search_tags' => 'tanggal lahir pensiun mbt date',
                    ],
                    [
                        'field' => 'agama',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Dropdown',
                        'type_sub' => 'Agama Resmi',
                        'is_dropdown' => true,
                        'description' => 'Pilih dari dropdown: Islam, Kristen Protestan, Katolik, Hindu, Buddha, atau Konghucu.',
                        'example' => 'Islam',
                        'search_tags' => 'agama islam kristen protestan katolik hindu buddha konghucu dropdown',
                    ],
                    [
                        'field' => 'susunan_keluarga',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Dropdown',
                        'type_sub' => 'Status PTKP Pajak',
                        'is_dropdown' => true,
                        'description' => 'Kode status tanggungan pajak: TK (Tidak Kawin), K/0, K/1, K/2, K/3.',
                        'example' => 'K/1',
                        'search_tags' => 'keluarga ptkp pajak tk k0 k1 k2 k3 dropdown',
                    ],
                ],
            ],
            [
                'id' => 'cat2',
                'title' => 'Kategori II • Jabatan & Formasi Organisasi (Kolom {range})',
                'icon' => 'fas fa-briefcase',
                'columns' => [
                    [
                        'field' => 'status_karyawan',
                        'short_title' => null,
                        'is_mandatory' => true,
                        'type_label' => 'Dropdown',
                        'type_sub' => 'Pilihan Validasi',
                        'is_dropdown' => true,
                        'description' => 'Pilih dari dropdown: Tetap (PKWTT), Kontrak (PKWT), atau Calon Pegawai.',
                        'example' => 'Tetap (PKWTT)',
                        'search_tags' => 'status karyawan pkwtt pkwt tetap kontrak calon dropdown',
                    ],
                    [
                        'field' => 'unit_kerja',
                        'short_title' => null,
                        'is_mandatory' => true,
                        'type_label' => 'Dropdown',
                        'type_sub' => 'Unit Resmi',
                        'is_dropdown' => true,
                        'description' => 'Pilih unit kerja resmi PTPN IV Regional V. Isi kolom ini lebih dulu: daftar Jabatan pada kolom berikutnya menyesuaikan unit yang dipilih.',
                        'example' => 'Kebun Inti Gunung Meliau',
                        'search_tags' => 'unit kebun pabrik pmo distrik regional dropdown',
                    ],
                    [
                        'field' => 'jabatan',
                        'short_title' => null,
                        'is_mandatory' => true,
                        'type_label' => 'Dropdown',
                        'type_sub' => 'Mengikuti Unit Kerja',
                        'is_dropdown' => true,
                        'description' => "Pilih setelah Unit Kerja. Dropdown hanya memuat Formasi Jabatan pada unit tersebut. Sheet 'Formasi per Unit' menampilkan strata, RM Band, bidang, kuota, dan sisa kuota setiap Formasi Jabatan. Strata (Karpim atau Karpel) dan RM Band diturunkan otomatis dari Formasi Jabatan, sehingga template tidak memiliki kolomnya. Karyawan Advisor atau Diperbantukan tetap wajib memiliki jabatan: pilih jabatan penampungannya (mis. Advisor RM-3) yang sudah dibuka pada unit.",
                        'example' => 'Asisten Afdeling',
                        'search_tags' => 'posisi nomenklatur formasi unit aktif dropdown jabatan sisa kuota',
                    ],
                    [
                        'field' => 'golongan',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Format Baku',
                        'type_sub' => '[Gol]/[Berkala]',
                        'is_dropdown' => false,
                        'description' => 'Format golongan: IA/00 s.d. IID/00 (Karpel) atau IIIA/00 s.d. IVD/05 (Karpim).',
                        'example' => 'IIIA/00',
                        'search_tags' => 'golongan berkala ia iid iiia ivd pangkat format baku',
                    ],
                    [
                        'field' => 'status_penugasan',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Dropdown',
                        'type_sub' => 'Status Jabatan Utama',
                        'is_dropdown' => true,
                        'description' => 'Status untuk jabatan pada kolom Jabatan, bukan untuk Tugas Rangkap. Default: Definitif. Pilihan lain: Pejabat Sementara (Pjs), Tugas Belajar. Advisor dan Diperbantukan adalah jabatan penampungan: pilih pada kolom Jabatan.',
                        'example' => 'Definitif',
                        'search_tags' => 'status jabatan utama penugasan definitif pjs diperbantukan tugas belajar advisor dropdown',
                    ],
                    [
                        'field' => 'tugas_rangkap_penugasan_sementara',
                        'short_title' => 'tugas_rangkap',
                        'is_mandatory' => false,
                        'type_label' => 'Teks Bebas',
                        'type_sub' => 'Auto-Register History',
                        'is_dropdown' => false,
                        'description' => 'Tugas rangkap (Plt/Pj/Pjs/Diperbantukan); statusnya dibaca dari teks ini, bukan dari kolom Status Jabatan Utama. Tulis satu tugas per baris (Alt+Enter) atau pisahkan dengan titik koma, dengan format: Plt. Manajer PKS Gunung Meliau TMT 15 Juli 2026, atau seperti hasil ekspor: [Plt] Jabatan — Unit (SK: nomor, TMT: 2026-07-15). Nama jabatan dan unit wajib sesuai master; bila tidak, baris ditolak. Bila unit tidak ditulis, dianggap unit karyawan sendiri, dan baris ditolak jika jabatan itu ada di unit lain pada struktur SK: tulis nama unitnya. Nomor SK pada teks hanya menjadi catatan, bukan nomor SK resmi. Tugas yang dihapus dari sel tidak diakhiri otomatis.',
                        'example' => 'Plt. Manajer PKS Gunung Meliau TMT 15 Juli 2026',
                        'search_tags' => 'tugas rangkap plt pjs riwayat job history',
                    ],
                ],
            ],
            [
                'id' => 'cat3',
                'title' => 'Kategori III • Masa Kerja & Rekam Karir (Kolom {range})',
                'icon' => 'fas fa-chart-line',
                'columns' => [
                    [
                        'field' => 'tmt_bekerja',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Tanggal (Date)',
                        'type_sub' => 'TMT Perdana',
                        'is_dropdown' => false,
                        'description' => 'Tanggal mulai tugas pertama kali bekerja. Wajib minimal 18 tahun setelah Tanggal Lahir (Aturan Batas Usia Kerja).',
                        'example' => '2010-01-01',
                        'search_tags' => 'tmt bekerja perdana tanggal mulai dinas date',
                    ],
                    [
                        'field' => 'tanggal_diangkat_staf',
                        'short_title' => 'tgl_angkat_staf',
                        'is_mandatory' => false,
                        'type_label' => 'Tanggal (Date)',
                        'type_sub' => 'Pengangkatan Karpim',
                        'is_dropdown' => false,
                        'description' => 'Tanggal pengangkatan resmi menjadi Karpim (Staf). Tidak boleh lebih awal dari TMT Bekerja.',
                        'example' => '2012-01-01',
                        'search_tags' => 'tanggal diangkat staf karpim pengangkatan date',
                    ],
                    [
                        'field' => 'tanggal_dalam_jabatan',
                        'short_title' => 'tgl_dlm_jabatan',
                        'is_mandatory' => false,
                        'type_label' => 'Tanggal (Date)',
                        'type_sub' => 'TMT Jabatan Saat Ini',
                        'is_dropdown' => false,
                        'description' => 'Tanggal penetapan pada jabatan yang diduduki saat ini. Tidak boleh mendahului TMT Bekerja.',
                        'example' => '2020-01-01',
                        'search_tags' => 'tanggal dalam jabatan tmt posisi saat ini date',
                    ],
                    [
                        'field' => 'tmt_unit_kerja',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Tanggal (Date)',
                        'type_sub' => 'TMT Unit Saat Ini',
                        'is_dropdown' => false,
                        'description' => 'Tanggal TMT penempatan dinas pada unit kerja saat ini.',
                        'example' => '2020-01-01',
                        'search_tags' => 'tmt unit kerja penempatan mutasi dinas date',
                    ],
                    [
                        'field' => 'jalur_masuk',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Dropdown',
                        'type_sub' => 'Rekrutmen',
                        'is_dropdown' => true,
                        'description' => 'Pilih dari dropdown: Reguler (Default), CKP (Calon Karyawan Pimpinan), Talent Scouting, atau RBB (Rekrutmen Bersama BUMN).',
                        'example' => 'Reguler',
                        'search_tags' => 'jalur masuk reguler ckp talent scouting rbb rekrutmen dropdown',
                    ],
                    [
                        'field' => 'job_grade',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Angka (1 s.d. 16)',
                        'type_sub' => 'Grade Jabatan',
                        'is_dropdown' => false,
                        'description' => 'Grade bobot posisi jabatan. Wajib konsisten dengan Strata: Karpel rentang 1 – 10, Karpim rentang 11 – 16 (Standar Grade Karpim/Karpel).',
                        'example' => '11',
                        'search_tags' => 'job grade bobot posisi 1 10 11 16 karpel karpim',
                    ],
                    [
                        'field' => 'person_grade',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Angka (1 s.d. 16)',
                        'type_sub' => 'Grade Individu',
                        'is_dropdown' => false,
                        'description' => 'Grade kompetensi karyawan. Wajib konsisten dengan Strata: Karpel rentang 1 – 10, Karpim rentang 11 – 16 (Standar Grade Karpim/Karpel).',
                        'example' => '11',
                        'search_tags' => 'person grade kompetensi individu 1 10 11 16 karpel karpim',
                    ],
                ],
            ],
            [
                'id' => 'cat4',
                'title' => 'Kategori IV • Pendidikan & Pensiun (Kolom {range})',
                'icon' => 'fas fa-graduation-cap',
                'columns' => [
                    [
                        'field' => 'pendidikan_terakhir',
                        'short_title' => 'pendidikan',
                        'is_mandatory' => false,
                        'type_label' => 'Dropdown',
                        'type_sub' => 'Jenjang Ijazah',
                        'is_dropdown' => true,
                        'description' => 'Pilih dari dropdown: SD, SMP, SMA/SMK, D1, D2, D3, D4 / S1, S2, atau S3.',
                        'example' => 'D4 / S1',
                        'search_tags' => 'pendidikan terakhir sd smp sma smk d3 d4 s1 s2 s3 ijazah dropdown',
                    ],
                    [
                        'field' => 'sekolah',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Teks Bebas',
                        'type_sub' => 'Institusi / Perguruan Tinggi',
                        'is_dropdown' => false,
                        'description' => 'Nama universitas, politeknik, institut, atau almamater.',
                        'example' => 'Universitas Tanjungpura',
                        'search_tags' => 'sekolah universitas perguruan tinggi institut almamater',
                    ],
                    [
                        'field' => 'tanggal_mbt',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Tanggal / Otomatis',
                        'type_sub' => 'Masa Bebas Tugas (H-6 Bulan)',
                        'is_dropdown' => false,
                        'description' => 'Tanggal Masa Bebas Tugas (6 bulan sebelum pensiun). Jika dikosongkan, dihitung otomatis dari tanggal lahir (Regulasi MBT H-6 Bulan).',
                        'example' => '2040-05-01',
                        'search_tags' => 'tanggal mbt masa bebas tugas 6 bulan sebelum pensiun date',
                    ],
                    [
                        'field' => 'tanggal_pensiun',
                        'short_title' => null,
                        'is_mandatory' => false,
                        'type_label' => 'Tanggal / Otomatis',
                        'type_sub' => 'Batas Usia Pensiun (56 Thn)',
                        'is_dropdown' => false,
                        'description' => 'Tanggal Batas Usia Pensiun normal (usia 56 tahun). Jika dikosongkan, dihitung otomatis dari tanggal lahir (Regulasi Pensiun BUP 56 Tahun).',
                        'example' => '2041-05-01',
                        'search_tags' => 'tanggal pensiun bup 56 tahun date batas usia',
                    ],
                ],
            ],
        ];
    }

    /**
     * Mengembalikan daftar 26 kolom dalam format datar (flat array).
     */
    public static function getColumns(): array
    {
        $columns = [];
        foreach (self::getCategories() as $category) {
            foreach ($category['columns'] as $col) {
                $col['category'] = $category['id'];
                $columns[] = $col;
            }
        }

        return $columns;
    }
}
