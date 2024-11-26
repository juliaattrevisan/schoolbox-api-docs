<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing;

use Sentry\Breadcrumb;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionSource;
use Throwable;

use function Sentry\continueTrace;
use function Sentry\getBaggage;
use function Sentry\getTraceparent;
use function Sentry\getW3CTraceparent;

/**
 * Defines a set of static methods for starting transactions and their stack,
 * and flushing them to Sentry.
 *
 * This won't push them until `finishTransaction` is called.
 */
class Tracer
{
    /**
     * A placeholder name for the transaction.
     */
    private const PLACEHOLDER_TRANSACTION_NAME = 'placeholder';

    /**
     * Singleton instance of the default trace to use.
     */
    private static ?Tracer $defaultTrace = null;

    /**
     * The current transaction context, if there is one.
     */
    private ?TransactionContext $transactionContext = null;

    /**
     * A set of stack changes, updated with `startSpan`, `finishSpan` and
     * `addBreadcrumb`.
     *
     * This set of changes is able to be flushed at any point.
     *
     * @var array<Operation\IOperation>
     */
    private array $stackChanges = [];

    /**
     * The set of spans within the current transaction.
     *
     * Unlike `$this->stackChanges`, this is not flushed: it must persist
     * between flushes.
     *
     * @var array<Span>
     */
    private array $spanStack = [];

    /**
     * Any server vars to associate with the trace: these will be added
     * automatically to transaction contexts.
     *
     * @var array
     */
    private array $serverVars = [];

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
     * Set server vars for this trace.
     *
     * @param array $serverVars
     * @return $this
     */
    public function setServerVars(array $serverVars): self
    {
        $this->serverVars = $serverVars;
        return $this;
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
     * @param string                 $name           The transaction's name: defaults to a placeholder value.
     *                                               This placeholder may be modified with one of the
     *                                               `setTransactionContextFor*` methods, or by calling `setName`
     *                                               directly on the context returned by `getTransactionContext`.
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
        string $name = self::PLACEHOLDER_TRANSACTION_NAME,
        ?string $operation = null,
        ?string $origin = null,
        ?TransactionSource $source = null,
        ?float $startTimestamp = null,
        array $data = [],
    ): TransactionContext {
        // Continue trace from headers or vars if present:
        // otherwise create a new trace
        if (isset($this->serverVars['HTTP_SENTRY_TRACE'], $this->serverVars['HTTP_BAGGAGE'])) {
            $transactionContext = continueTrace($this->serverVars['HTTP_SENTRY_TRACE'], $this->serverVars['HTTP_BAGGAGE']);
        } else {
            $transactionContext = new TransactionContext();
        }
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
        $this->spanStack          = [];
        $this->transactionContext = $transactionContext;
        return $transactionContext;
    }

    /**
     * @return TransactionContext|null
     */
    public function getTransactionContext(): ?TransactionContext
    {
        return $this->transactionContext;
    }

    /**
     * Helper function for updating the current transaction context with
     * settings for tracing an Aura route.
     *
     * @param string $route The name of the Aura route.
     * @return void
     */
    public function setTransactionContextForAuraRoute(string $route): void
    {
        $transactionContext = $this->transactionContext;
        if ($transactionContext) {
            $transactionContext->setName('aura:' . $route);
            $transactionContext->setOp('http.server');
            $transactionContext->setOrigin('auto.http.aura');
            $transactionContext->setSource(TransactionSource::route());
            $transactionContext->setData(
                $transactionContext->getData() + [
                    'url'                 => $this->serverVars['REQUEST_URI'] ?? null,
                    'http.request.method' => $this->serverVars['REQUEST_METHOD'] ?? null,
                ],
            );
        }
    }

    /**
     * Helper function for updating the current transaction context with
     * settings for tracing a SitePage page load.
     *
     * @param string $filename The path of the filename being served: either a
     *                         full path, or the path relative to the site
     *                         root.
     * @return void
     */
    public function setTransactionContextForSitePage(string $filename): void
    {
        $transactionContext = $this->transactionContext;
        if ($transactionContext) {
            $transactionContext->setName('sitePage:' . self::normaliseFilename($filename));
            $transactionContext->setOp('http.server');
            $transactionContext->setOrigin('auto.http.sitePage');
            $transactionContext->setSource(TransactionSource::url());
            $transactionContext->setData(
                $transactionContext->getData() + [
                    'url'                 => $this->serverVars['REQUEST_URI'] ?? null,
                    'http.request.method' => $this->serverVars['REQUEST_METHOD'] ?? null,
                ],
            );
        }
    }

    /**
     * Helper function for updating the current transaction context with
     * settings for tracing a basic request.
     *
     * @param string $filename The path of the filename being served: either a
     *                         full path, or the path relative to the site
     *                         root.
     * @return void
     */
    public function setTransactionContextForBasicRequest(string $filename): void
    {
        $transactionContext = $this->transactionContext;
        if ($transactionContext) {
            $transactionContext->setName('basicRequest:' . self::normaliseFilename($filename));
            $transactionContext->setOp('http.server');
            $transactionContext->setOrigin('auto.http.basicRequest');
            $transactionContext->setSource(TransactionSource::url());
            $transactionContext->setData(
                $transactionContext->getData() + [
                    'url'                 => $this->serverVars['REQUEST_URI'] ?? null,
                    'http.request.method' => $this->serverVars['REQUEST_METHOD'] ?? null,
                ],
            );
        }
    }

    /**
     * Helper function for updating the current transaction context with
     * settings for tracing an invocation of a command line tool.
     *
     * @param string $filename The full path of the file being invoked.
     * @return void
     */
    public function setTransactionContextForCommandLineTool(string $filename): void
    {
        $transactionContext = $this->transactionContext;
        if ($transactionContext) {
            $transactionContext->setName('cli:' . self::normaliseFilename($filename));
            $transactionContext->setOp('console.command');
            $transactionContext->setOrigin('auto.console');
            $transactionContext->setSource(TransactionSource::task());

            // Process data, from
            // https://github.com/open-telemetry/semantic-conventions/blob/main/docs/resource/process.md
            $transactionContext->setData(
                $transactionContext->getData() + [
                    'process.command_args' => $this->serverVars['argv'] ?? null,
                    'process.owner'        => get_current_user(),
                    'process.pid'          => getmypid(),
                ],
            );
        }
    }

    /**
     * Helper function for updating the current transaction context with
     * settings for tracing a message received by a daemon.
     *
     * @param string $destination    An identifier for the queue which received
     *                               the message.
     * @param string $subscriptionId A subscription ID for the queue?
     * @param string $messageId      An ID for the message received.
     * @return void
     */
    public function setTransactionContextForDaemon(string $destination, string $subscriptionId, string $messageId): void
    {
        $transactionContext = $this->transactionContext;
        if ($transactionContext) {
            $transactionContext->setName('consume ' . $destination);
            $transactionContext->setOp('message.receive');
            $transactionContext->setOrigin('auto.message');
            $transactionContext->setSource(TransactionSource::task());

            // Messaging data, from
            // https://github.com/open-telemetry/semantic-conventions/blob/main/docs/messaging/messaging-spans.md
            $transactionContext->setData(
                $transactionContext->getData() + [
                    'messaging.destination.name'     => $subscriptionId,
                    'messaging.destination.template' => $destination,
                    'messaging.operation.name'       => 'consume',
                    'messaging.message.id'           => $messageId,
                ],
            );
        }
    }

    /**
     * Normalises a full filename path for use in a transaction name, by
     * stripping away the site root.
     *
     * @param string $filename
     * @return string
     */
    private static function normaliseFilename(string $filename): string
    {
        return trim(str_replace(SITE_root, '', $filename), '/');
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
     * Add a breadcrumb.
     *
     * @see Breadcrumb::__construct for description of parameters.
     *
     * @param string      $level
     * @param string      $type
     * @param string      $category
     * @param string|null $message
     * @param array       $metadata
     * @param float|null  $timestamp
     * @return void
     */
    public function addBreadcrumb(
        string $level,
        string $type,
        string $category,
        ?string $message = null,
        array $metadata = [],
        ?float $timestamp = null,
    ): void {
        $this->stackChanges[] = new Operation\Breadcrumb(
            new Breadcrumb($level, $type, $category, $message, $metadata, $timestamp),
        );
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
        $this->startSpan($description, $operation, $origin, null, $data);
        try {
            $result = $callable();
            $this->finishSpan(status: SpanStatus::ok());
            return $result;
        } catch (Throwable $e) {
            $this->finishSpan(status: SpanStatus::internalError());
            throw $e;
        }
    }

    /**
     * Finish any existing span, reverting the Sentry hub back to tracking the
     * current transaction.
     *
     * @param float|null      $endTimestamp When the span is to be recorded as
     *                                      having finished.
     * @param SpanStatus|null $status       The status to set on the span once
     *                                      it has finished.
     *
     * @return void
     */
    public function finishSpan(?float $endTimestamp = null, ?SpanStatus $status = null): void
    {
        $spanContext = new SpanContext();
        $spanContext->setEndTimestamp($endTimestamp ?? microtime(true));
        $spanContext->setStatus($status);
        $this->stackChanges[] = new Operation\Pop($spanContext);
    }

    /**
     * Flush all stack changes into Sentry's transaction.
     *
     * @param bool            $finish       If true, finish the transaction:
     *                                      if false, keep it open for future
     *                                      stack changes.
     * @param float|null      $endTimestamp Timestamp to end the transaction on.
     *                                      Only relevant if `$finish` is true.
     * @param SpanStatus|null $status       Status of the transaction.
     *                                      Only relevant if `$finish` is true.
     * @return void
     */
    private function flushTransaction(
        bool $finish,
        ?float $endTimestamp = null,
        ?SpanStatus $status = null,
    ): void {
        if ($this->transactionContext instanceof TransactionContext) {
            $hub = SentrySdk::getCurrentHub();

            /** @var array<Span> $spanStack */
            $spanStack   = [];
            $transaction = $hub->getTransaction();
            if (!$transaction) {
                // Create a new transaction
                $transaction = $hub->startTransaction($this->transactionContext);
                if (!$transaction->getSampled()) {
                    return;
                }

                // Autostart a span:
                // close it only upon finishing the transaction
                $spanContext = new SpanContext();
                $spanContext->setDescription($transaction->getName());
                $spanContext->setOp($transaction->getOp());
                $spanContext->setOrigin($transaction->getOrigin());
                $spanContext->setStartTimestamp($transaction->getStartTimestamp());
                $span = $transaction->startChild($spanContext);
                $hub->setSpan($span);
                $this->spanStack[] = $span;
            }

            // Apply each change to the actual transaction stack.
            foreach ($this->stackChanges as $stackChange) {
                $spanStack = $stackChange->operate($hub, $spanStack);
            }
            $this->stackChanges = [];

            if ($finish) {
                // End any leftover spans (including the autostarted span, if there)
                $endTimestamp ??= microtime(true);
                while (!empty($this->spanStack)) {
                    $leftoverSpan = array_pop($this->spanStack);
                    $leftoverSpan->finish($endTimestamp);
                }

                $transaction->setStatus($status);
                $transaction->finish($endTimestamp);
            }
        }
    }

    /**
     * Finish any existing transaction, if there is one, and writes its trace to
     * the Sentry hub.
     *
     * Also finishes any spans which may still be open.
     *
     * @param float|null        $endTimestamp When the transactions is to be
     *                                        recorded as having finished.
     * @param SpanStatus|null   $status       The transaction status to set once
     *                                        it has finished.
     * @return void
     */
    public function finishTransaction(?float $endTimestamp = null, ?SpanStatus $status = null): void
    {
        $this->flushTransaction(true, $endTimestamp, $status);
    }

    /**
     * Write the transaction to date, then get the traceparent string.
     *
     * @see getTraceparent()
     *
     * @return string
     */
    public function getTraceparent(): string
    {
        $this->flushTransaction(false);
        return getTraceparent();
    }

    /**
     * Write the transaction to date, then get the W3C traceparent string.
     *
     * @see getW3CTraceparent()
     *
     * @return string
     */
    public function getW3CTraceparent(): string
    {
        $this->flushTransaction(false);
        return getW3CTraceparent();
    }

    /**
     * Write the transaction to date, then get the baggage string.
     *
     * @see getBaggage()
     *
     * @return string
     */
    public function getBaggage(): string
    {
        $this->flushTransaction(false);
        return getBaggage();
    }
}
