<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Pelanggaran aturan master formasi; pesannya aman ditampilkan kepada admin.
 */
class FormasiRuleViolationException extends InvalidArgumentException {}
