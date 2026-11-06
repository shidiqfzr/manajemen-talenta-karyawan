<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Impor pegawai dibatalkan dengan pesan yang aman ditampilkan kepada admin.
 * Kendala internal (galat basis data, pustaka spreadsheet) dicatat ke log dan diganti pesan umum
 * agar rincian teknis (SQL, path server) tidak sampai ke peramban.
 */
class ImportAbortedException extends RuntimeException {}
