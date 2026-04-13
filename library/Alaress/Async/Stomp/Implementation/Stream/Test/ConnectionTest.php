<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream\Test;

use Alaress\Async\Stomp\Implementation\Stream\Connection;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexStreamInterface;
use React\Stream\ThroughStream;
use Stomp\Protocol\Protocol;
use Stomp\Protocol\Version;
use Stomp\Transport\Parser;

#[CoversMethod(Connection::class, 'onClose')]
class ConnectionTest extends TestCase
{
    /**
     * Create dummy connection with a ThroughStream instead of a real socket.
     *
     * @return Connection
     */
    private function makeConnection(): Connection
    {
        return new class (
            $this->createMock(LoopInterface::class),
            new Protocol(null, Version::VERSION_1_1),
            new Parser(),
            'localhost',
            61613,
            'guest',
            'guest',
            '/',
        ) extends Connection {
            private ?ThroughStream $nextStream = null;

            public function prepareStream(ThroughStream $stream): void
            {
                $this->nextStream = $stream;
            }

            protected function createStream(string $host, int $port): DuplexStreamInterface
            {
                return $this->nextStream ?? new ThroughStream();
            }
        };
    }

    /**
     * Test that a connection's close callback fires whenever its stream closes
     * for whatever reason.
     */
    public function testOnCloseCallbackFiredWhenStreamCloses(): void
    {
        $conn   = $this->makeConnection();
        $stream = new ThroughStream();
        /** @noinspection PhpPossiblePolymorphicInvocationInspection - prepareStream is defined on innner class */
        $conn->prepareStream($stream);

        $fired = false;
        $conn->onClose(function () use (&$fired): void {
            $fired = true;
        });

        $conn->connect();
        $stream->close();

        $this->assertTrue($fired);
    }

    /**
     * Test that all on-close callbacks are fired whenever a connection's stream
     * closes.
     */
    public function testMultipleOnCloseCallbacksAllFire(): void
    {
        $conn   = $this->makeConnection();
        $stream = new ThroughStream();
        /** @noinspection PhpPossiblePolymorphicInvocationInspection - prepareStream is defined on innner class */
        $conn->prepareStream($stream);

        $count = 0;
        $conn->onClose(function () use (&$count): void {
            $count++;
        });
        $conn->onClose(function () use (&$count): void {
            $count++;
        });

        $conn->connect();
        $stream->close();

        $this->assertSame(2, $count);
    }

    /**
     * Test that a connection's connect method does not return the same promise
     * before and after its stream closes.
     *
     * This is required because:
     * * the connect method's promise will be a resolved promise after a
     *   successful connection
     * * if a disconnect happens, a resolved promise is no longer appropriate as
     *   the connection in the initial promise is gone
     */
    public function testConnectionPromiseResetAfterClose(): void
    {
        $conn   = $this->makeConnection();
        $stream = new ThroughStream();
        /** @noinspection PhpPossiblePolymorphicInvocationInspection - prepareStream is defined on innner class */
        $conn->prepareStream($stream);

        $promise1 = $conn->connect();
        $stream->close();
        $promise2 = $conn->connect();

        $this->assertNotSame($promise1, $promise2);
    }

    /**
     * Test that destroying a connection object without actually connecting does
     * not raise an error.
     */
    public function testDestructWithoutConnectDoesNotThrow(): void
    {
        $conn = $this->makeConnection();
        unset($conn);
        $this->addToAssertionCount(1);
    }

    /**
     * Test that the connection's on-close callback doesn't trigger before the
     * stream closes.
     */
    public function testOnCloseNotFiredBeforeStreamCloses(): void
    {
        $conn   = $this->makeConnection();
        $stream = new ThroughStream();
        /** @noinspection PhpPossiblePolymorphicInvocationInspection - prepareStream is defined on innner class */
        $conn->prepareStream($stream);

        $fired = false;
        $conn->onClose(function () use (&$fired): void {
            $fired = true;
        });

        $conn->connect();
        // Do NOT close the stream

        $this->assertFalse($fired);
    }
}
