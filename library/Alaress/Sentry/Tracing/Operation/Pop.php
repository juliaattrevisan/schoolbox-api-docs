<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing\Operation;

use Sentry\Tracing\SpanStatus;

/**
 * A representation of a pop from the transaction stack.
 *
 * The status and end timestamp are usually not known until the transaction or
 * span is complete: thus those changes are encapsulated here, when the span
 * finishes.
 */
class Pop
{
    /**
     * Constructor
     *
     * @param float|null      $endTimestamp The time to end the current span.
     * @param SpanStatus|null $status       The status with which to end the
     *                                      current span.
     */
    public function __construct(public ?float $endTimestamp = null, public ?SpanStatus $status = null)
    {
    }
}
