<?php

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream\Test;

use Alaress\Async\Stomp\Implementation\Stream\Connection;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexStreamInterface;
use React\Stream\ThroughStream;
use Stomp\Protocol\Protocol;
use Stomp\Protocol\Version;
use Stomp\Transport\Parser;

/**
 * Subclass that injects a ThroughStream instead of opening a real socket.
 */
class TestableConnection extends Connection
{
    private ?ThroughStream $nextStream = null;

    public function prepareStream(ThroughStream $stream): void
    {
        $this->nextStream = $stream;
    }

    protected function createStream(string $host, int $port): DuplexStreamInterface
    {
        return $this->nextStream ?? new ThroughStream();
    }
}

class ConnectionTest extends TestCase
{
    private function makeConnection(): TestableConnection
    {
        return new TestableConnection(
            $this->createMock(LoopInterface::class),
            new Protocol(null, Version::VERSION_1_1),
            new Parser(),
            'localhost',
            61613,
            'guest',
            'guest',
            '/'
        );
    }

    public function testOnCloseCallbackFiredWhenStreamCloses(): void
    {
        $conn   = $this->makeConnection();
        $stream = new ThroughStream();
        $conn->prepareStream($stream);

        $fired = false;
        $conn->onClose(function () use (&$fired): void {
            $fired = true;
        });

        $conn->connect();
        $stream->close();

        $this->assertTrue($fired);
    }

    public function testMultipleOnCloseCallbacksAllFire(): void
    {
        $conn   = $this->makeConnection();
        $stream = new ThroughStream();
        $conn->prepareStream($stream);

        $count = 0;
        $conn->onClose(function () use (&$count): void { $count++; });
        $conn->onClose(function () use (&$count): void { $count++; });

        $conn->connect();
        $stream->close();

        $this->assertSame(2, $count);
    }

    public function testConnectionPromiseResetAfterClose(): void
    {
        $conn    = $this->makeConnection();
        $stream1 = new ThroughStream();
        $conn->prepareStream($stream1);

        $promise1 = $conn->connect();

        $stream2 = new ThroughStream();
        $conn->prepareStream($stream2);
        $stream1->close();

        $promise2 = $conn->connect();

        $this->assertNotSame($promise1, $promise2);
    }

    public function testOnCloseNotFiredBeforeStreamCloses(): void
    {
        $conn   = $this->makeConnection();
        $stream = new ThroughStream();
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
