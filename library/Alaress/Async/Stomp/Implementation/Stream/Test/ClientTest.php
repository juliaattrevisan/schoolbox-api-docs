<?php

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream\Test;

use Alaress\Async\Stomp\Implementation\Stream\Client;
use Alaress\Async\Stomp\Implementation\Stream\Connection;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversMethod(Client::class, 'onDisconnect')]
#[CoversMethod(Connection::class, 'onClose')]
class ClientTest extends TestCase
{
    public function testOnDisconnectDelegatesToConnectionOnClose(): void
    {
        $conn = $this->createMock(Connection::class);
        $cb   = function (): void {};

        $conn->expects($this->once())
            ->method('onClose')
            ->with($cb);

        $client = new Client($conn);
        $client->onDisconnect($cb);
    }
}
