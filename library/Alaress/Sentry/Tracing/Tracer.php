<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing;

use Sentry\State\HubInterface;
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

    public static function startTransaction(
        string $name,
        ?string $operation = null,
        ?string $origin = null,
        ?TransactionSource $source = null,
        ?float $startTimestamp = null,
        array $data = [],
    ): TransactionContext {
        // Clear the current transaction, I guess?
        self::$transactionContainer = null;
        self::$currentSpanContainer = null;

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
        self::$transactionContainer = new SpanContainer($transactionContext, null);
        self::$currentSpanContainer = self::$transactionContainer;

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

        // Put this into the current span container
        $spanContainer = new SpanContainer($spanContext, self::$currentSpanContainer);

        // This should update the child context within the parent as well?
        self::$currentSpanContainer->addChildContext($spanContext);

        self::$currentSpanContainer = $spanContainer;

        return $spanContext;
    }

    public static function finishSpan(?float $endTimestamp = null): void
    {
        // Find span from current container, set its end timestamp
        self::$currentSpanContainer?->spanContext->setEndTimestamp($endTimestamp ?? microtime(true));

        // Set the current container back to the parent of the current container
        self::$currentSpanContainer = self::$currentSpanContainer?->parent;
    }

    public static function finishTransaction(HubInterface $hub, ?float $endTimestamp = null): void
    {
        // Write the transaction and its spans into the hub
        if (self::$transactionContainer) {
            $transactionContext = self::$transactionContainer->spanContext;
            if ($transactionContext instanceof TransactionContext) {
                $transactionContext->setEndTimestamp($endTimestamp ?? microtime(true));
                $transaction = $hub->startTransaction($transactionContext);
                self::$transactionContainer->flush($hub, $transaction);
            }
        }
    }
}
