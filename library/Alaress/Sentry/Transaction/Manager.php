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
     * TODO determine if more than one span can be open?
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
        $this->finishSpan();

        $spanContext = new SpanContext();
        $spanContext->setDescription($description);
        $spanContext->setOp($operation);
        $spanContext->setOrigin($origin);
        $spanContext->setData($data);

        $span = $this->transaction->startChild($spanContext);
        if (!$span->getSampled()) {
            return;
        }

        $this->span = $span;

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
        if (!$this->span) {
            return;
        }
        $this->span->finish();

        /** @noinspection PhpExpressionResultUnusedInspection - this method mutates */
        $this->hub->setSpan($this->transaction);
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
