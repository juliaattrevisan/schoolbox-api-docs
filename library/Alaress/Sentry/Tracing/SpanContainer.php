<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing;

use Sentry\State\HubInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;

/**
 * Data structure for containing:
 * * a span context
 * * its children
 *
 * for later hydration into a transaction.
 */
class SpanContainer
{
    /**
     * @var array<SpanContainer>
     */
    private array $childContainers = [];

    /**
     * Constructor
     *
     * @param SpanContext        $spanContext The context which this is a container for.
     * @param SpanContainer|null $parent      A reference back to the parent of this container, if there is one.
     */
    public function __construct(public SpanContext $spanContext, public ?SpanContainer $parent = null)
    {
    }

    /**
     * Add a child context.
     *
     * @param SpanContext $spanContext
     * @return self
     */
    public function addChildContext(SpanContext $spanContext): self
    {
        $this->childContainers[] = new SpanContainer($spanContext, $this);
        return $this;
    }

//    public function findLastChildContainer(): ?SpanContainer
//    {
//        if (empty($this->childContainers)) {
//            return null;
//        }
//
//        // Find the last context.
//        // If that is empty: return that one; otherwise, return its last child.
//        $lastContainer = array_pop($this->childContainers);
//        $this->childContainers[] = $lastContainer;
//    }

    /**
     * Flush this context.
     *
     * @param HubInterface $hub
     * @param Span         $parent
     * @return void
     */
    public function flush(HubInterface $hub, ?Span $parent): void
    {
        $span = $parent->startChild($this->spanContext);
        $hub->setSpan($span);
        foreach ($this->childContainers as $childContainer) {
            $childContainer->flush($hub, $span);
        }
        $span->finish();
    }
}
