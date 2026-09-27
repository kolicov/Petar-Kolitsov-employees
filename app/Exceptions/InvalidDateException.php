<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class InvalidDateException extends InvalidArgumentException
{
    public static function for(string $value): self
    {
        return new self(sprintf("invalid date '%s'", $value));
    }
}
