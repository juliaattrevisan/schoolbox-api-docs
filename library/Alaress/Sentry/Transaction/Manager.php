<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Transaction;

use Sentry\State\HubInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionSource;
use Throwable;

/**
 * Sentry transaction manager: handles currently open transaction and span for
 * the current profile.
 */
class Manager
{
    /**
     * A Sentry transaction: used for instrumenting this kernel and sending its
     * trace to Sentry.
     */
    private ?Transaction $transaction = null;

    /**
     * The stack of currently open spans.
     *
     * Starting a span will add it to the stack; finishing it will remove it.
     *
     * @var array<Span>
     */
    private array $spans = [];

    /**
     * Constructor
     *
     * @param HubInterface $hub The Sentry connection which the transactions and
     *                          spans will be created against.
     */
    public function __construct(private HubInterface $hub)
    {
    }

    /**
     * Start a new transaction.
     *
     * This should only be called when a new request is created and handled, as
     * a transaction represents a single request to a service: see
     * https://docs.sentry.io/concepts/key-terms/tracing/distributed-tracing/#traces-transactions-and-spans.
     *
     * @param string                 $name           The transaction's name: this should be something which describes
     *                                               the request which spawned the transaction, e.g. a route name or
     *                                               request URI.
     * @param string|null            $description    A description of the transaction: e.g. a database query,
     *                                               route name, request URL.
     *                                               Neither the Laravel nor Symfony implementations seem to set a
     *                                               transaction description, only its name.
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
     * @param bool                   $autostartSpan  If true: auto-start a root span with matching details for this
     *                                               transaction, with matching details.
     *                                               This prevents the transaction from closing when a manually created
     *                                               span is closed.
     * @return void
     */
    public function startTransaction(
        string $name,
        ?string $description = null,
        ?string $operation = null,
        ?string $origin = null,
        ?TransactionSource $source = null,
        ?float $startTimestamp = null,
        array $data = [],
        bool $autostartSpan = true,
    ): void {
        // No concurrent transactions
        $this->finishTransaction();

        $transactionContext = new TransactionContext();
        $transactionContext->setName($name);
        $transactionContext->setDescription($description);
        $transactionContext->setOp($operation);
        $transactionContext->setOrigin($origin);
        if ($source) {
            $transactionContext->setSource($source);
        }
        $transactionContext->setStartTimestamp($startTimestamp);
        $transactionContext->setData($data);

        $transaction = $this->hub->startTransaction($transactionContext);

        /** @noinspection PhpExpressionResultUnusedInspection - this method mutates */
        $this->hub->setSpan($transaction);

        // Transaction not sampled: no need to set anything
        if (!$transaction->getSampled()) {
            return;
        }
        $this->transaction = $transaction;

        if ($autostartSpan) {
            $this->startSpan($name, $operation, $origin, $startTimestamp, $data);
        }
    }

    /**
     * Start a new span.
     *
     * This span will be created within the transaction's currently active span,
     * or the transaction itself if it does not exist.
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
     * @return void
     */
    public function startSpan(
        string $description,
        ?string $operation = null,
        ?string $origin = null,
        ?float $startTimestamp = null,
        array $data = [],
    ): void {
        if (!$this->transaction) {
            return;
        }
        $spanContext = new SpanContext();
        $spanContext->setDescription($description);
        $spanContext->setOp($operation);
        $spanContext->setOrigin($origin);
        $spanContext->setStartTimestamp($startTimestamp);
        $spanContext->setData($data);

        // Create new span within most recent span,
        // or within transaction if there is no current span
        $parent = array_pop($this->spans);
        if ($parent) {
            $span          = $parent->startChild($spanContext);
            $this->spans[] = $parent;
        } else {
            $span = $this->transaction->startChild($spanContext);
        }
        $this->spans[] = $span;

        /** @noinspection PhpExpressionResultUnusedInspection - this method mutates */
        $this->hub->setSpan($span);
    }

    /**
     * Finish any existing span, reverting the Sentry hub back to tracking the
     * current transaction.
     *
     * @return void
     */
    public function finishSpan(): void
    {
        if (!$this->transaction) {
            return;
        }

        // Finish current span
        $span = array_pop($this->spans);
        $span?->finish();

        // Set current span to next most recent span,
        // or transaction if there is none
        $parent = array_pop($this->spans);
        if ($parent) {
            $this->spans[] = $parent;
        } else {
            $parent = $this->transaction;
        }
        /** @noinspection PhpExpressionResultUnusedInspection - this method mutates */
        $this->hub->setSpan($parent);
    }

    /**
     * Run `$callable` within the entirety of a new span.
     *
     * @see startSpan for description of other parameters.
     *
     * @param callable    $callable
     * @param string|null $description
     * @param string|null $operation
     * @param string|null $origin
     * @param array       $data
     * @return mixed Returns whatever `$callable` does when run.
     * @throws Throwable May throw whatever `$callable` may.
     */
    public function runWithinSpan(
        callable $callable,
        ?string $description = null,
        ?string $operation = null,
        ?string $origin = null,
        array $data = [],
    ): mixed {
        $this->startSpan($description, $operation, $origin, $data);
        try {
            return $callable();
        } finally {
            $this->finishSpan();
        }
    }

    /**
     * Finish any existing transaction, if there is one.
     *
     * Also finishes any spans which may still be open.
     *
     * @return void
     */
    public function finishTransaction(): void
    {
        while (!empty($this->spans)) {
            $span = array_pop($this->spans);
            $span->finish();
        }
        $this->transaction?->finish();
    }
}
