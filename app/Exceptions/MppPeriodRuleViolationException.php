<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Pelanggaran aturan penutupan periode MPP; pesannya aman ditampilkan kepada admin.
 */
class MppPeriodRuleViolationException extends InvalidArgumentException {}
