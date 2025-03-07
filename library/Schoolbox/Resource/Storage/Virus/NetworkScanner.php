<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Virus;

/**
 * Virus scanner implementation, which uses a network-available `clamd` daemon.
 *
 * @see https://docs.clamav.net/manual/Usage/Scanning.html#daemon
 */
class NetworkScanner implements IScanner
{
    public function __construct(
        private ?string $sharedDirectory,
        private ?string $clamDaemonHost,
        private ?int $clamDaemonPort,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function scan(string $fileLocation): void
    {
    }

    /**
     * @inheritdoc
     */
    public function checkExists(): string
    {
        if (!isset($this->sharedDirectory, $this->clamDaemonHost, $this->clamDaemonPort)) {
            return self::STATUS_UNAVAILABLE;
        }
        if (!extension_loaded('sockets')) {
            return self::STATUS_UNAVAILABLE;
        }

        // Can the port be accessed?
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($socket, $this->clamDaemonHost, $this->clamDaemonPort);
        socket_write($socket, 'PING');

        // Wait for a response from the socket for up to 5s:
        // said response should be "PONG"
        $time     = microtime(true);
        $timeout  = $time + 5;
        $response = '';
        while (microtime(true) < $timeout) {
            $response .= socket_read($socket, 1024);
            if (trim($response) === 'PONG') {
                socket_close($socket);
                return self::STATUS_ONLINE;
            }
            usleep(100000);
        }
        return self::STATUS_OFFLINE;
    }
}
