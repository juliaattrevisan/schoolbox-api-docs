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
 * A representation of a push onto the transaction stack.
 */
class Push implements IOperation
{
    /**
     * Constructor
     *
     * @param SpanContext $context The span context to push.
     */
    public function __construct(public SpanContext $context)
    {
    }

    /**
     * Push `$this->context` onto the hub, and add it to the returned stack.
     *
     * @param HubInterface $hub
     * @param array<Span>  $spanStack
     * @return array<Span>
     */
    public function operate(HubInterface $hub, array $spanStack): array
    {
        $currentSpan = !empty($spanStack)
            ? $spanStack[count($spanStack) - 1]
            : $hub->getTransaction();

        $span = $currentSpan->startChild($this->context);
        $hub->setSpan($span);
        $spanStack[] = $span;
        return $spanStack;
    }
}
