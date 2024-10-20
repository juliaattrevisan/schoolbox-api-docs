<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing\Operation;

use Sentry\Tracing\SpanContext;

/**
 * A representation of a push onto the transaction stack.
 */
class Push
{
    public function __construct(public SpanContext $context)
    {
    }
}
