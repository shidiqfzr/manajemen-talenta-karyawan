<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan SK struktur organisasi. Pesan berbahasa Indonesia dan boleh ditampilkan kepada admin.
 */
class StructureDecreeException extends RuntimeException {}
