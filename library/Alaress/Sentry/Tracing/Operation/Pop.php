<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing\Operation;

use Sentry\Tracing\SpanContext;

/**
 * A representation of a pop from the transaction stack, with optional override
 * of parts of the span context pushed onto the stack.
 */
class Pop
{
    /**
     * Constructor
     *
     * @param SpanContext $context The new context for the span: any values in
     *                             this context which are set will override what
     *                             was set in `Push`.
     *                             Usually, at least the status and end
     *                             timestamp will be overridden.
     */
    public function __construct(public SpanContext $context)
    {
    }
}
