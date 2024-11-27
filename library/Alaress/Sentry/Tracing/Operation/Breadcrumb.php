<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing\Operation;

use Sentry\Breadcrumb as SentryBreadcrumb;
use Sentry\State\HubInterface;
use Sentry\Tracing\Span;

/**
 * A representation of an addition of a breadcrumb onto the transaction stack.
 */
class Breadcrumb implements IOperation
{
    /**
     * Constructor
     *
     * @param SentryBreadcrumb $breadcrumb The breadcrumb to add to the stack.
     */
    public function __construct(private SentryBreadcrumb $breadcrumb)
    {
    }

    /**
     * Add `$this->breadcrumb` to the hub, but otherwise does not modify the
     * stack.
     *
     * @param HubInterface $hub
     * @param array<Span>  $spanStack
     * @return array<Span>
     */
    public function operate(HubInterface $hub, array $spanStack): array
    {
        $hub->addBreadcrumb($this->breadcrumb);
        return $spanStack;
    }
}
