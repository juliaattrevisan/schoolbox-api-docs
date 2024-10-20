<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing\Operation;

/**
 * A representation of a pop from the transaction stack.
 */
class Pop
{
    public function __construct(public ?float $endTimestamp = null)
    {
    }
}
