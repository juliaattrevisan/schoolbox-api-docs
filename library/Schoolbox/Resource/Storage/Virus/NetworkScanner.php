<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Virus;

use Schoolbox\File\Exception as FileException;

/**
 * Virus scanner implementation, which uses a network-available `clamd` daemon.
 *
 * @see https://docs.clamav.net/manual/Usage/Scanning.html#daemon
 */
class NetworkScanner implements IScanner
{
    private const DELIMITER = "\0";

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
        // Copy file to temp, presuming it exists
        if (!file_exists($fileLocation)) {
            return;
        }
        $tempFile = tempnam($this->sharedDirectory, '');
        $copied   = copy($fileLocation, $tempFile);
        if (!$copied) {
            return;
        }

        // For now make said file world-readable (for clamav host)
        chmod($tempFile, 0644);

        // Create socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($socket, $this->clamDaemonHost, $this->clamDaemonPort);

        // Send request to scan file,
        // presuming that file path is the same on the clamav host
        socket_write($socket, 'zSCAN ' . $tempFile . self::DELIMITER);

        // Wait for a response: but what?
        $time     = microtime(true);
        $timeout  = $time + 10;
        $response = '';
        while (microtime(true) < $timeout) {
            $response .= socket_read($socket, 1024);
            if (str_ends_with($response, self::DELIMITER)) {
                $response = trim($response);
                if (str_ends_with($response, 'OK')) {
                    socket_close($socket);
                    return;
                }
                // Any other response: assume a virus
                socket_close($socket);
                throw new FileException(
                    sprintf('Virus scanner received response "%s": presuming virus', $response)
                );
            }
            usleep(100000);
        }
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
        socket_write($socket, 'zPING' . self::DELIMITER);

        // Wait for a response from the socket for up to 5s:
        // said response should be "PONG"
        $time     = microtime(true);
        $timeout  = $time + 5;
        $response = '';
        while (microtime(true) < $timeout) {
            $response .= socket_read($socket, 1024);

            // If last byte is the delimiter: we have a response
            if (str_ends_with($response, self::DELIMITER)) {
                if (trim($response) === 'PONG') {
                    socket_close($socket);
                    return self::STATUS_ONLINE;
                }
            }
            usleep(100000);
        }
        socket_close($socket);
        return self::STATUS_OFFLINE;
    }
}
