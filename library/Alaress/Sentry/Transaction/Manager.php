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
     * The current span within the transaction, if there is one.
     */
    private ?Span $span = null;

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
     * Start a new transaction, finishing an existing one if there is one
     * already open.
     *
     * @param string                 $name
     * @param string|null            $description
     * @param string|null            $operation
     * @param string|null            $origin
     * @param TransactionSource|null $source
     * @param float|null             $startTimestamp
     * @param array                  $data
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
    }

    /**
     * Start a new span, finishing an existing one if there is one already open.
     *
     * @param string|null $description
     * @param string|null $operation
     * @param string|null $origin
     * @param array       $data
     * @return void
     */
    public function startSpan(
        ?string $description = null,
        ?string $operation = null,
        ?string $origin = null,
        array $data = [],
    ): void {
        if (!$this->transaction) {
            return;
        }
        $spanContext = new SpanContext();
        $spanContext->setDescription($description);
        $spanContext->setOp($operation);
        $spanContext->setOrigin($origin);
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
        if ($span) {
            $span->finish();
        }

        // Set current span to next most recent span,
        // or transaction if there is none
        $parent = array_pop($this->spans);
        if ($parent) {
            $this->spans[] = $parent;
        } else {
            $parent  = $this->transaction;
        }
        /** @noinspection PhpExpressionResultUnusedInspection - this method mutates */
        $this->hub->setSpan($parent);
    }

    /**
     * Run `$callable` within the entirety of a new span.
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
     * @return void
     */
    public function finishTransaction(): void
    {
        $this->transaction?->finish();
    }
}
