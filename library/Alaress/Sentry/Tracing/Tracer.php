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
use Throwable;

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
     * A set of stack changes, updated with `startSpan` and `finishSpan`.
     *
     * @var array<Operation\Pop|Operation\Push>
     */
    private array $stackChanges = [];

    /**
     * Any server vars to associate with the trace: these will be added
     * automatically to transaction contexts.
     *
     * @var array
     */
    private array $serverVars = [];

    /**
     * Upon starting a transaction: should a span be automatically started which
     * encompasses the entire transaction?
     *
     * (If a transaction is created without any spans, it shows in Sentry as a
     *  transaction without any time span.)
     */
    private bool $autostartSpan = true;

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
     * @param bool                   $autostartSpan  If true: auto-start a root span with matching details for this
     *                                               transaction, with matching details.
     *                                               This prevents the transaction from closing when a manually created
     *                                               span is closed.
     * @return TransactionContext
     */
    public function startTransaction(
        string $name = self::PLACEHOLDER_TRANSACTION_NAME,
        ?string $operation = null,
        ?string $origin = null,
        ?TransactionSource $source = null,
        ?float $startTimestamp = null,
        array $data = [],
        bool $autostartSpan = true,
    ): TransactionContext {
        $this->autostartSpan = $autostartSpan;
        $transactionContext  = new TransactionContext();
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
            return $callable();
        } finally {
            $this->finishSpan();
        }
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

            // Autostart a span, if requested
            if ($this->autostartSpan) {
                $spanContext = new SpanContext();
                $spanContext->setDescription($transaction->getName());
                $spanContext->setOp($transaction->getOp());
                $spanContext->setOrigin($transaction->getOrigin());
                $spanContext->setStartTimestamp($transaction->getStartTimestamp());
                $span = $transaction->startChild($spanContext);
                $hub->setSpan($span);
                $spanStack[] = $span;
            }

            // Apply each change to the actual transaction stack.
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

            // End any leftover spans (including the autostarted span, if there)
            $endTimestamp ??= microtime(true);
            while (!empty($spanStack)) {
                $leftoverSpan = array_pop($spanStack);
                $leftoverSpan->finish($endTimestamp);
            }

            $transaction->finish($endTimestamp);
        }
    }
}
