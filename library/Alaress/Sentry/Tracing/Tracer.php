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
 * This won't push them until `finishTransaction` is called.
 */
class Tracer
{
    /**
     * Singleton instance of the default trace to use.
     */
    private static ?Tracer $defaultTrace = null;

    /**
     * The current transaction context, if there is one.
     */
    private ?TransactionContext $transactionContext = null;

    /**
     * A set of stack changes, updated with `startSpan` and `finishSpan`.
     *
     * @var array<Operation\Pop|Operation\Push>
     */
    private array $stackChanges = [];

    /**
     * Build and return the default trace object.
     *
     * @return self
     */
    public static function getDefaultTrace(): self
    {
        self::$defaultTrace ??= new Tracer();
        return self::$defaultTrace;
    }

    /**
     * Create a new transaction context, and add it to the internal stack.
     *
     * This will only be added to Sentry when `finishTransaction` is called.
     *
     * This should only be called when a new request is created and handled, as
     * a transaction represents a single request to a service: see
     * https://docs.sentry.io/concepts/key-terms/tracing/distributed-tracing/#traces-transactions-and-spans.
     *
     * @param string                 $name           The transaction's name: this should be something which describes
     *                                               the request which spawned the transaction, e.g. a route name or
     *                                               request URI.
     *                                               This is also used as the auto-started transaction's description.
     * @param string|null            $operation      The operation which the transaction is tracking: see
     *                                               https://develop.sentry.dev/sdk/telemetry/traces/span-operations/
     *                                               for standard examples.
     * @param string|null            $origin         The action which created the transaction: see
     *                                               https://develop.sentry.dev/sdk/telemetry/traces/trace-origin/
     *                                               for how this value may be created.
     * @param TransactionSource|null $source         How the transaction's name was generated.
     * @param float|null             $startTimestamp When the transaction is to be recorded as having started:
     *                                               usually, this will be the start time of the request, i.e.
     *                                               `$_SERVER['REQUEST_TIME_FLOAT']`.
     * @param array                  $data           Additional data to add to the transaction: see
     *                                               https://develop.sentry.dev/sdk/telemetry/traces/span-data-conventions/
     *                                               for standard examples.
     * @return TransactionContext
     */
    public function startTransaction(
        string $name,
        ?string $operation = null,
        ?string $origin = null,
        ?TransactionSource $source = null,
        ?float $startTimestamp = null,
        array $data = [],
    ): TransactionContext {

        $transactionContext = new TransactionContext();
        $transactionContext->setName($name);
        $transactionContext->setOp($operation);
        $transactionContext->setOrigin($origin);
        if ($source) {
            $transactionContext->setSource($source);
        }
        $transactionContext->setStartTimestamp($startTimestamp ?? microtime(true));
        $transactionContext->setData($data);

        // Clear the current transaction state, before setting a new one.
        $this->stackChanges       = [];
        $this->transactionContext = $transactionContext;
        return $transactionContext;
    }

    /**
     * Start a new span.
     *
     * This span will be created within the last started span, or the
     * transaction itself if it does not exist.
     *
     * If the transaction does not exist at all (because it is not yet created,
     * or it was created but is not being sampled), this does nothing.
     *
     * @param string      $description    A description of the span: e.g. a database query, route name, request URL.
     * @param string|null $operation      The operation which the span is tracking: see
     *                                    https://develop.sentry.dev/sdk/telemetry/traces/span-operations/
     *                                    for standard examples.
     * @param string|null $origin         The action which created the span: see
     *                                    https://develop.sentry.dev/sdk/telemetry/traces/trace-origin/
     *                                    for how this value may be created.
     * @param float|null  $startTimestamp When the span is to be recorded as having started:
     *                                    usually, this will be the start time of the request, i.e.
     *                                    `$_SERVER['REQUEST_TIME_FLOAT']`.
     * @param array       $data           Additional data to add to the span: see
     *                                    https://develop.sentry.dev/sdk/telemetry/traces/span-data-conventions/
     *                                    for standard examples.
     * @return SpanContext
     */
    public function startSpan(
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

        $this->stackChanges[] = new Operation\Push($spanContext);
        return $spanContext;
    }

    /**
     * Finish any existing span, reverting the Sentry hub back to tracking the
     * current transaction.
     *
     * @param float|null $endTimestamp When the span is to be recorded as having
     *                                 finished.
     *
     * @return void
     */
    public function finishSpan(?float $endTimestamp = null): void
    {
        $this->stackChanges[] = new Operation\Pop($endTimestamp ?? microtime(true));
    }

    /**
     * Finish any existing transaction, if there is one, and writes its trace to
     * the Sentry hub.
     *
     * Also finishes any spans which may still be open.
     *
     * @param HubInterface $hub          The Sentry hub to write the trace to.
     * @param float|null   $endTimestamp When the transactions is to be
     *                                   recorded as having finished.
     * @return void
     */
    public function finishTransaction(HubInterface $hub, ?float $endTimestamp = null): void
    {
        // Write the transaction and its spans into the hub
        if ($this->transactionContext instanceof TransactionContext) {
            $transaction = $hub->startTransaction($this->transactionContext);
            if (!$transaction->getSampled()) {
                return;
            }

            /** @var array<Span> $spanStack */
            $spanStack = [];
            foreach ($this->stackChanges as $stackChange) {
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

            // End any leftover spans?
            $endTimestamp ??= microtime(true);
            while (!empty($spanStack)) {
                $leftoverSpan = array_pop($spanStack);
                $leftoverSpan->finish($endTimestamp);
            }

            $transaction->finish($endTimestamp);
        }
    }
}
