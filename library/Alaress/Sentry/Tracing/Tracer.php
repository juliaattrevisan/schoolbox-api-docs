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
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionSource;

/**
 * Defines a set of static methods for starting transactions and their stack,
 * and flushing them to Sentry.
 *
 * This won't push them just yet.
 */
class Tracer
{
//    private ?TransactionContext $transactionContext = null;
//
//    /**
//     * @var array{
//     *     span: SpanContext,
//     *     children:
//     * }
//     */
//    private static array $spans;

    public static ?SpanContainer $transactionContainer = null;

    public static ?SpanContainer $currentSpanContainer = null;

    private static ?TransactionContext $transactionContext = null;

    /**
     * @var array<Operation\Pop|Operation\Push>
     */
    private static array $stackChanges = [];

    public static function startTransaction(
        string $name,
        ?string $operation = null,
        ?string $origin = null,
        ?TransactionSource $source = null,
        ?float $startTimestamp = null,
        array $data = [],
    ): TransactionContext {
        // Clear the current transaction, I guess?
//        self::$transactionContainer = null;
//        self::$currentSpanContainer = null;
        self::$stackChanges = [];

        $transactionContext = new TransactionContext();
        $transactionContext->setName($name);
        $transactionContext->setOp($operation);
        $transactionContext->setOrigin($origin);
        if ($source) {
            $transactionContext->setSource($source);
        }
        $transactionContext->setStartTimestamp($startTimestamp ?? microtime(true));
        $transactionContext->setData($data);

        // Put this transaction context somewhere,
        // so it can be found with startSpan
//        self::$transactionContainer = new SpanContainer($transactionContext, null);
//        self::$currentSpanContainer = self::$transactionContainer;

        self::$transactionContext = $transactionContext;
        return $transactionContext;
    }

    public static function startSpan(
        string $description,
        ?string $operation = null,
        ?string $origin = null,
        ?float $startTimestamp = null,
        array $data = [],
    ): SpanContext {
        $spanContext = new SpanContext();
        $spanContext->setDescription($description);
        $spanContext->setOp($operation);
        $spanContext->setOrigin($origin);
        $spanContext->setStartTimestamp($startTimestamp ?? microtime(true));
        $spanContext->setData($data);

        self::$stackChanges[] = new Operation\Push($spanContext);

//        // Put this into the current span container
//        $spanContainer = new SpanContainer($spanContext, self::$currentSpanContainer);
//
//        // This should update the child context within the parent as well?
//        self::$currentSpanContainer->addChildContext($spanContext);
//
//        self::$currentSpanContainer = $spanContainer;

        return $spanContext;
    }

    public static function finishSpan(?float $endTimestamp = null): void
    {
        // Find span from current container, set its end timestamp
        self::$stackChanges[] = new Operation\Pop($endTimestamp ?? microtime(true));

//        self::$currentSpanContainer?->spanContext->setEndTimestamp($endTimestamp ?? microtime(true));
//
//        // Set the current container back to the parent of the current container
//        self::$currentSpanContainer = self::$currentSpanContainer?->parent;
    }

    public static function finishTransaction(HubInterface $hub, ?float $endTimestamp = null): void
    {
        // Write the transaction and its spans into the hub
        if (self::$transactionContext instanceof TransactionContext) {
            $transaction = $hub->startTransaction(self::$transactionContext);

            /** @var array<Span> $spanStack */
            $spanStack = [];
            foreach (self::$stackChanges as $stackChange) {
                if ($stackChange instanceof Operation\Push) {
                    // Start context in most recently added stack,
                    // or transaction if there is no stack
                    $currentSpan = !empty($spanStack)
                        ? $spanStack[count($spanStack) - 1]
                        : $transaction;

                    $span = $currentSpan->startChild($stackChange->context);
                    $hub->setSpan($span);
                    $spanStack[] = $span;
                } elseif ($stackChange instanceof Operation\Pop) {
                    // Finish current stack, if there is one
                    $currentSpan = array_pop($spanStack);
                    $currentSpan->finish($stackChange->endTimestamp);

                    $previousSpan = !empty($spanStack)
                        ? $spanStack[count($spanStack) - 1]
                        : $transaction;
                    $hub->setSpan($previousSpan);
                }
            }

//            // End any remaining spans?
//            while (!empty($spanStack)) {
//                $leftoverSpan = array_pop($spanStack);
//            }

            $transaction->finish($endTimestamp ?? microtime(true));
        }
    }
}
