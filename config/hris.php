<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tata Kelola Impor Data Karyawan
    |--------------------------------------------------------------------------
    |
    | require_approval  : kebijakan empat mata (maker-checker). Bila aktif, impor tidak langsung disimpan:
    |                     admin pengaju mengajukan hasil pratinjau, lalu admin LAIN menyetujui atau menolak.
    | approval_ttl_days : pengajuan yang tidak diputuskan dalam batas hari ini kedaluwarsa otomatis.
    | undo_window_days  : batas hari pembatalan (undo) impor setelah disimpan. Rekaman data sebelum impor
    |                     (terenkripsi) dihapus setelah batas ini sebagai minimisasi data pribadi.
    | typed_confirmation_threshold : pembatalan impor yang menyentuh sekian karyawan atau lebih wajib
    |                     dikonfirmasi dengan mengetik kata BATALKAN (mencegah penghapusan massal tak sengaja).
    |
    */

    'import' => [
        'require_approval' => (bool) env('HRIS_IMPORT_REQUIRE_APPROVAL', false),
        'approval_ttl_days' => (int) env('HRIS_IMPORT_APPROVAL_TTL_DAYS', 7),
        'undo_window_days' => (int) env('HRIS_IMPORT_UNDO_WINDOW_DAYS', 7),
        'typed_confirmation_threshold' => (int) env('HRIS_IMPORT_TYPED_CONFIRMATION_THRESHOLD', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tata Kelola Usulan Mutasi Berantai (SK Kolektif)
    |--------------------------------------------------------------------------
    |
    | require_approval  : kebijakan empat mata (maker-checker). Bila aktif, admin penyusun hanya dapat
    |                     menyimpan draf dan mengajukan; SK diterbitkan setelah admin LAIN menyetujui.
    |                     Bila nonaktif, penyusun yang berhak menyetujui dapat langsung menerbitkan SK.
    | approval_ttl_days : usulan yang tidak diputuskan dalam batas hari ini kedaluwarsa otomatis.
    |
    */

    'mutasi_chain' => [
        'require_approval' => (bool) env('HRIS_MUTASI_CHAIN_REQUIRE_APPROVAL', false),
        'approval_ttl_days' => (int) env('HRIS_MUTASI_CHAIN_APPROVAL_TTL_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hak Akses per Peran
    |--------------------------------------------------------------------------
    |
    | Hak akses => daftar peran (kolom users.role) yang memilikinya. Didaftarkan sebagai Gate di
    | AppServiceProvider (App\Support\HrisPermission). Peran baru cukup ditambahkan di sini, mis.
    | 'mutasi.prepare' => ['admin', 'hr_staff'] dan 'mutasi.approve' => ['admin', 'hr_manager'].
    |
    | mutasi.prepare : menyusun, menyimpan draf, dan mengajukan usulan mutasi berantai.
    | mutasi.approve : menyetujui/menolak usulan, atau langsung menerbitkan SK bila persetujuan nonaktif.
    |
    */

    'permissions' => [
        'mutasi.prepare' => ['admin'],
        'mutasi.approve' => ['admin'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paginasi Tabel Data
    |--------------------------------------------------------------------------
    |
    | Satu standar jumlah baris per halaman untuk seluruh tabel data, agar jumlah halaman
    | konsisten di setiap modul. Modul tidak boleh menetapkan nilainya sendiri.
    |
    | default : jumlah baris per halaman saat tabel pertama kali dibuka.
    | options : pilihan jumlah baris yang tampil pada menu "Tampilkan"; harus memuat nilai default.
    |
    */

    'pagination' => [
        'default' => 25,
        'options' => [10, 25, 50, 100],
    ],

    /*
    |--------------------------------------------------------------------------
    | Label Peran
    |--------------------------------------------------------------------------
    |
    | Nama peran yang tampil pada menu pengguna. Peran baru cukup ditambahkan di sini;
    | peran yang belum terdaftar tampil sebagai nama perannya sendiri.
    |
    */

    'role_labels' => [
        'admin' => 'Administrator SDM',
        'user' => 'Karyawan',
    ],

];
