<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Test\Tracing;

use Alaress\Sentry\Tracing\Tracer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Tracing\Span;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * Tests that the tracer's span stack survives a partial flush.
 *
 * A partial flush is triggered whenever tracing headers are attached to an
 * outgoing Guzzle request (see `GuzzleTracingMiddleware::trace`), which happens
 * part way through a span rather than at a stack boundary.
 */
#[CoversClass(Tracer::class)]
class TracerTest extends TestCase
{
    /**
     * The hub in use before this test replaced it.
     */
    private ?HubInterface $originalHub = null;

    /**
     * Any warnings raised while the test ran.
     *
     * @var array<string>
     */
    private array $warnings = [];

    /**
     * Install a hub which samples every transaction, and capture any warning
     * raised by the trace operations.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->originalHub = SentrySdk::getCurrentHub();

        $client = ClientBuilder::create([
            'dsn'                  => 'https://public@example.com/1',
            'traces_sample_rate'   => 1.0,
            // Keep Sentry's error and exception listeners out of the test run
            'default_integrations' => false,
        ])
            ->setTransport($this->buildNullTransport())
            ->getClient();
        SentrySdk::setCurrentHub(new Hub($client));

        $this->warnings = [];
        set_error_handler(
            function (int $errno, string $message): bool {
                $this->warnings[] = $message;
                return true;
            },
            E_USER_WARNING,
        );
    }

    /**
     * Restore the hub and error handler for other tests.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        restore_error_handler();
        SentrySdk::setCurrentHub($this->originalHub);
    }

    /**
     * A transaction whose spans open and close without an intervening partial
     * flush must not warn.
     *
     * This is the control for `testPartialFlushMidSpanDoesNotWarn`: it isolates
     * the partial flush as the trigger.
     *
     * @return void
     */
    public function testBalancedSpansDoNotWarn(): void
    {
        $tracer = new Tracer();
        $tracer->startTransaction('test');

        $tracer->startSpan('outer');
        $tracer->finishSpan();

        $tracer->finishTransaction();

        $this->assertSame([], $this->warnings);
    }

    /**
     * A span which is still open when tracing headers are read must still be
     * matched with its pop once the transaction finishes.
     *
     * Reproduces SA-21702: `getTraceparent` partially flushes the stack changes
     * queued so far, so the push for `outer` is applied during that flush while
     * its pop is applied during the final flush.
     *
     * @return void
     */
    public function testPartialFlushMidSpanDoesNotWarn(): void
    {
        $tracer = new Tracer();
        $tracer->startTransaction('test');

        $tracer->startSpan('outer');

        // Stands in for GuzzleTracingMiddleware attaching tracing headers to an
        // outgoing request made from within `outer`.
        $tracer->getTraceparent();
        $tracer->getW3CTraceparent();
        $tracer->getBaggage();

        $tracer->finishSpan();

        $tracer->finishTransaction();

        $this->assertSame([], $this->warnings);
    }

    /**
     * A span started after a partial flush belongs to the span which was open
     * at the time of that flush, not to the transaction.
     *
     * @return void
     */
    public function testSpanStartedAfterPartialFlushIsChildOfOpenSpan(): void
    {
        $tracer = new Tracer();
        $tracer->startTransaction('test');

        $tracer->startSpan('outer');
        $tracer->getTraceparent();

        // Stands in for the span the middleware records once the response to
        // that request arrives.
        $tracer->startSpan('inner');
        $tracer->finishSpan();

        $tracer->finishSpan();
        $tracer->finishTransaction();

        $outer = $this->findSpanByDescription('outer');
        $inner = $this->findSpanByDescription('inner');

        $this->assertSame(
            (string)$outer->getSpanId(),
            (string)$inner->getParentSpanId(),
            'Span started after a partial flush was not parented to the open span',
        );
    }

    /**
     * Find a recorded span on the current transaction by its description.
     *
     * @param string $description
     * @return Span
     */
    private function findSpanByDescription(string $description): Span
    {
        $transaction = SentrySdk::getCurrentHub()->getTransaction();
        $this->assertNotNull($transaction, 'No transaction was started');

        $spans = $transaction->getSpanRecorder()?->getSpans() ?? [];
        foreach ($spans as $span) {
            if ($span->getDescription() === $description) {
                return $span;
            }
        }

        $this->fail(sprintf('No span recorded with description "%s"', $description));
    }

    /**
     * Build a transport which discards everything, so that the test never
     * reaches the network.
     *
     * @return TransportInterface
     */
    private function buildNullTransport(): TransportInterface
    {
        return new class implements TransportInterface {
            public function send(Event $event): Result
            {
                return new Result(ResultStatus::success(), $event);
            }

            public function close(?int $timeout = null): Result
            {
                return new Result(ResultStatus::success());
            }
        };
    }
}
