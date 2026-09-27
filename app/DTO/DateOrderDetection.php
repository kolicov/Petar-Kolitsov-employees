<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\DateOrder;
use App\Enums\DateOrderReason;

/**
 * How a file's ambiguous numeric dates (01/02/2013) were read, and why.
 */
final readonly class DateOrderDetection
{
    /**
     * @param  string|null  $ambiguousExample  The first date in the file that could be read both ways; null if none.
     * @param  int|null  $evidenceLine  Line of the first date that proved the order (only when detected).
     * @param  string|null  $evidence  That date, e.g. "25/02/2013".
     */
    public function __construct(
        public DateOrder $order,
        public DateOrderReason $reason,
        public ?string $ambiguousExample = null,
        public ?int $evidenceLine = null,
        public ?string $evidence = null,
    ) {}

    /**
     * One sentence for the user, or null when the file has no ambiguous dates.
     */
    public function summary(): ?string
    {
        if ($this->ambiguousExample === null) {
            return null;
        }

        $reason = match ($this->reason) {
            DateOrderReason::Detected => sprintf('detected from line %d: %s', $this->evidenceLine, $this->evidence),
            DateOrderReason::Default => 'the default; the file gives no evidence',
            DateOrderReason::Conflicting => 'the default; the file contains both day-first and month-first dates',
        };

        return sprintf('Ambiguous dates like %s were read as %s (%s).', $this->ambiguousExample, $this->order->label(), $reason);
    }
}
