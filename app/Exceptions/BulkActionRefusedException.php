<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by a bulk handler that refuses to run (for example, too many target rows) before it changes
 * anything. The surrounding transaction is rolled back; the message is meant for the user.
 */
class BulkActionRefusedException extends RuntimeException
{
    /** An expected refusal, not an error: nothing to log. */
    public function report(): void {}
}
