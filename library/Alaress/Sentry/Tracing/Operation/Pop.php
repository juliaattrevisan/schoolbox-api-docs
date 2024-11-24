<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing\Operation;

use Sentry\State\HubInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;

/**
 * A representation of a pop from the transaction stack, with optional override
 * of parts of the span context pushed onto the stack.
 */
class Pop implements IOperation
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

    /**
     * Pop the most recent span off of `$spanStack`, and override its end
     * timestamp (and optional status) with values `$this->context` before
     * finishing that span and reverting to the previous span.
     *
     * @param HubInterface $hub
     * @param array<Span>  $spanStack
     * @return array<Span>
     */
    public function operate(HubInterface $hub, array $spanStack): array
    {
        // Finish current stack, if there is one:
        // if there isn't, do nothing (raise a warning, I guess)
        $currentSpan = array_pop($spanStack);
        if (!$currentSpan instanceof Span) {
            trigger_error(
                'Attempted trace pop operation on an empty stack: check there is a matching push operation',
                E_USER_WARNING,
            );
            return [];
        }

        if ($this->context->getStatus()) {
            $currentSpan->setStatus($this->context->getStatus());
        }
        $currentSpan->finish($this->context->getEndTimestamp());

        $previousSpan = !empty($spanStack)
            ? $spanStack[count($spanStack) - 1]
            : $hub->getTransaction();
        $hub->setSpan($previousSpan);
        return $spanStack;
    }
}
