<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Master data needed to execute a mutation is inconsistent
 * (e.g. the occupant of the target formation has no active job history).
 */
class MutasiIntegrityException extends RuntimeException {}
