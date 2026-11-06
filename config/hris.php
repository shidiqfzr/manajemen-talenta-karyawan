<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tata Kelola Impor Data Pegawai
    |--------------------------------------------------------------------------
    |
    | require_approval  : kebijakan empat mata (maker-checker). Bila aktif, impor tidak langsung disimpan:
    |                     admin pengaju mengajukan hasil pratinjau, lalu admin LAIN menyetujui atau menolak.
    | approval_ttl_days : pengajuan yang tidak diputuskan dalam batas hari ini kedaluwarsa otomatis.
    | undo_window_days  : batas hari pembatalan (undo) impor setelah disimpan. Rekaman data sebelum impor
    |                     (terenkripsi) dihapus setelah batas ini sebagai minimisasi data pribadi.
    | typed_confirmation_threshold : pembatalan impor yang menyentuh sekian pegawai atau lebih wajib
    |                     dikonfirmasi dengan mengetik kata BATALKAN (mencegah penghapusan massal tak sengaja).
    |
    */

    'import' => [
        'require_approval' => (bool) env('HRIS_IMPORT_REQUIRE_APPROVAL', false),
        'approval_ttl_days' => (int) env('HRIS_IMPORT_APPROVAL_TTL_DAYS', 7),
        'undo_window_days' => (int) env('HRIS_IMPORT_UNDO_WINDOW_DAYS', 7),
        'typed_confirmation_threshold' => (int) env('HRIS_IMPORT_TYPED_CONFIRMATION_THRESHOLD', 50),
    ],

];
