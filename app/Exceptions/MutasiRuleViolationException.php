<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * A mutation breaks a business rule of the HR governance (tenure, promotion path, reserved formation, ...).
 *
 * The message is written in business language and is safe to show to the administrator.
 * Extends InvalidArgumentException so existing callers keep working.
 */
class MutasiRuleViolationException extends InvalidArgumentException {}
