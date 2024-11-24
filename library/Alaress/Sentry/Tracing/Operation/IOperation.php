<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing\Operation;

use Sentry\State\HubInterface;
use Sentry\Tracing\Span;

/**
 * An encapsulation of an operation on the Sentry tracing stack.
 */
interface IOperation
{
    /**
     * Operate upon the span stack.
     *
     * @param HubInterface $hub       The Sentry interface to interact with: spans will be set on this object.
     * @param array<Span>  $spanStack The stack of spans added to the hub: this will be used to track the last added
     *                                span, so that it may be set as the hub's current span later on.
     *                                (This needs to be tracked outside `$hub` because that object doesn't itself
     *                                maintain a stack.)
     * @return array<Span>
     */
    public function operate(HubInterface $hub, array $spanStack): array;
}
