<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a file's ambiguous dates were read in a given order.
 */
enum DateOrderReason
{
    /** The file only contains dates that prove one order. */
    case Detected;

    /** The file gives no evidence, so the configured default is used. */
    case Default;

    /** The file proves both orders, so the configured default is used. */
    case Conflicting;
}
