<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A single CSV row is invalid; the row is skipped and reported as a warning.
 */
final class InvalidRowException extends RuntimeException {}
