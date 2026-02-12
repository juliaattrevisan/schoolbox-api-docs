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
     * The server var which contains the value of the `sentry-trace` HTTP header
     * for this request.
     *
     * Used along with the `baggage` header for passing and continuing a
     * distributed trace.
     *
     * @see https://docs.sentry.io/platforms/javascript/tracing/trace-propagation/
     */
    public const SERVER_VAR_SENTRY_TRACE = 'HTTP_SENTRY_TRACE';

    /**
     * The server var which contains the value of the `baggage` HTTP header for
     * this request.
     *
     * Used along with the `sentry-trace` header for passing and continuing a
     * distributed trace.
     *
     * @see https://docs.sentry.io/platforms/javascript/tracing/trace-propagation/
     */
    public const SERVER_VAR_BAGGAGE = 'HTTP_BAGGAGE';

    /**
     * The environment var which contains the Sentry trace value for this
     * process.
     *
     * Used along with the `BAGGAGE` env var for passing and continuing a
     * distributed trace.
     *
     * @see https://docs.sentry.io/platforms/javascript/tracing/trace-propagation/
     */
    public const ENV_VAR_SENTRY_TRACE = 'SENTRY_TRACE';

    /**
     * The environment var which contains the baggage value for this process.
     *
     * Used along with the `SENTRY_TRACE` env var for passing and continuing a
     * distributed trace.
     *
     * @see https://docs.sentry.io/platforms/javascript/tracing/trace-propagation/
     */
    public const ENV_VAR_BAGGAGE = 'BAGGAGE';

    /**
     * A placeholder name for the transaction.
     */
    private const PLACEHOLDER_TRANSACTION_NAME = 'placeholder';

    /**
     * Singleton instance of the default trace to use.
     */
    private static ?Tracer $defaultTrace = null;

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
        $transactionContext = null;
        if (isset($this->serverVars[self::SERVER_VAR_SENTRY_TRACE], $this->serverVars[self::SERVER_VAR_BAGGAGE])) {
            $transactionContext = continueTrace(
                $this->serverVars[self::SERVER_VAR_SENTRY_TRACE],
                $this->serverVars[self::SERVER_VAR_BAGGAGE],
            );
        } else {
            $envSentryTrace = getenv(self::ENV_VAR_SENTRY_TRACE, true);
            $envBaggage     = getenv(self::ENV_VAR_BAGGAGE, true);
            if ($envSentryTrace && $envBaggage) {
                $transactionContext = continueTrace($envSentryTrace, $envBaggage);
            }
        }
        $transactionContext ??= new TransactionContext();
        $transactionContext->setName($name);
        $transactionContext->setOp($operation);
        $transactionContext->setOrigin($origin);
        if ($source) {
            $transactionContext->setSource($source);
        }
        $transactionContext->setStartTimestamp($startTimestamp ?? microtime(true));
        $transactionContext->setData(
            $data + MemoryHelper::buildStartSpanData(),
        );

        // Clear the current transaction state, before setting a new one.
        $this->stackChanges = [];
        $this->spanStack    = [];

        // Also start the transaction, so that profiling can begin.
        $transaction = SentrySdk::getCurrentHub()->startTransaction($transactionContext);
        SentrySdk::getCurrentHub()->setSpan($transaction);
        return $transactionContext;
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
        $transaction = SentrySdk::getCurrentHub()->getTransaction();
        if ($transaction) {
            $transaction->setName('aura:' . $route);
            $transaction->setOp('http.server');
            $transaction->setOrigin('auto.http.aura');
            $transaction->getMetadata()->setSource(TransactionSource::route());
            $transaction->setData(
                $transaction->getData() + [
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
        $transaction = SentrySdk::getCurrentHub()->getTransaction();
        if ($transaction) {
            $transaction->setName('sitePage:' . self::normaliseFilename($filename));
            $transaction->setOp('http.server');
            $transaction->setOrigin('auto.http.sitePage');
            $transaction->getMetadata()->setSource(TransactionSource::url());
            $transaction->setData(
                $transaction->getData() + [
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
        $transaction = SentrySdk::getCurrentHub()->getTransaction();
        if ($transaction) {
            $transaction->setName('basicRequest:' . self::normaliseFilename($filename));
            $transaction->setOp('http.server');
            $transaction->setOrigin('auto.http.basicRequest');
            $transaction->getMetadata()->setSource(TransactionSource::url());
            $transaction->setData(
                $transaction->getData() + [
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
        $transaction = SentrySdk::getCurrentHub()->getTransaction();
        if ($transaction) {
            $transaction->setName('cli:' . self::normaliseFilename($filename));
            $transaction->setOp('console.command');
            $transaction->setOrigin('auto.console');
            $transaction->getMetadata()->setSource(TransactionSource::task());

            // Process data, from
            // https://github.com/open-telemetry/semantic-conventions/blob/main/docs/resource/process.md
            $transaction->setData(
                $transaction->getData() + [
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
        $transaction = SentrySdk::getCurrentHub()->getTransaction();
        if ($transaction) {
            $transaction->setName('consume ' . $destination);
            $transaction->setOp('message.receive');
            $transaction->setOrigin('auto.message');
            $transaction->getMetadata()->setSource(TransactionSource::task());

            // Messaging data, from
            // https://github.com/open-telemetry/semantic-conventions/blob/main/docs/messaging/messaging-spans.md
            $transaction->setData(
                $transaction->getData() + [
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
     * @param string      $description    A human-readable description of the span: e.g. a database query, route name,
     *                                    request URL.
     *                                    There should be enough detail within the description to relate this span back
     *                                    to a root cause.
     *                                    This relates back to the OpenTelemetry span's "name" concept:
     *                                    https://develop.sentry.dev/sdk/telemetry/traces/opentelemetry/#span-protocol
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
        $spanContext->setData($data + MemoryHelper::buildStartSpanData());

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
     * @param callable                           $callable
     * @param string|null                        $description
     * @param string|null                        $operation
     * @param string|null                        $origin
     * @param array                              $data
     * @param (callable(mixed): array)|null      $onSuccess If provided, a callable which runs on success:
     *                                                      it takes the result of `$callable`, and returns data to add
     *                                                      to the span.
     *  @param (callable(Throwable): array)|null $onFailure If provided, a callable which runs on failure:
     *                                                      it takes what `$callable` threw, and returns data to add to
     *                                                      the span.
     * @return mixed Returns whatever `$callable` does when run.
     * @throws Throwable May throw whatever `$callable` may.
     */
    public function runWithinSpan(
        callable $callable,
        ?string $description = null,
        ?string $operation = null,
        ?string $origin = null,
        array $data = [],
        ?callable $onSuccess = null,
        ?callable $onFailure = null,
    ): mixed {
        $this->startSpan($description, $operation, $origin, null, $data);
        try {
            $result = $callable();
            $this->finishSpan(
                status: SpanStatus::ok(),
                data:   is_callable($onSuccess) ? $onSuccess($result) : [],
            );
            return $result;
        } catch (Throwable $e) {
            $this->finishSpan(
                status: SpanStatus::internalError(),
                data:   is_callable($onFailure) ? $onFailure($e) : [],
            );
            throw $e;
        }
    }

    /**
     * Finish any existing span, reverting the Sentry hub back to tracking the
     * current transaction.
     *
     * @param float|null      $endTimestamp When the span is to be recorded as having finished.
     * @param SpanStatus|null $status       The status to set on the span once it has finished.
     * @param array           $data         Any data to add to the span now that it has finished.
     *                                      Memory usage data will automatically be added, if not overridden by this
     *                                      parameter.
     *
     * @return void
     */
    public function finishSpan(?float $endTimestamp = null, ?SpanStatus $status = null, array $data = []): void
    {
        $spanContext = new SpanContext();
        $spanContext->setEndTimestamp($endTimestamp ?? microtime(true));
        $spanContext->setStatus($status);
        $spanContext->setData($data + MemoryHelper::buildEndSpanData());
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
        $hub = SentrySdk::getCurrentHub();

        /** @var array<Span> $spanStack */
        $spanStack   = [];
        $transaction = $hub->getTransaction();

        // No transaction or not sampled? Return
        if (!($transaction && $transaction->getSampled())) {
            return;
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

            // Add memory usage at transaction end
            $transaction->setData(MemoryHelper::buildEndSpanData());
            MemoryHelper::addMemoryDeltaData($transaction);

            $transaction->setStatus($status);
            $transaction->finish($endTimestamp);
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
