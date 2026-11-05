<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Pelanggaran aturan rencana pasokan MPP; pesannya aman ditampilkan kepada admin.
 */
class MppPlanRuleViolationException extends InvalidArgumentException {}
