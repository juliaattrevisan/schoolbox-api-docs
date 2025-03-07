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
    private const COMMAND_PREFIX = 'z';

    private const DELIMITER = "\0";

    /**
     * Constructor
     *
     * @param string|null $sharedDirectory The shared directory on this host to copy files to.
     * @param string|null $clamDaemonHost  The hostname the ClamAV daemon is available on.
     * @param int|null    $clamDaemonPort  The port the ClamAV daemon is listening on.
     */
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
            throw new DaemonException('File does not exist');
        }
        $tempFile = tempnam($this->sharedDirectory, '');
        if (!$tempFile) {
            throw new DaemonException('Failed to create temp file');
        }
        $copied = copy($fileLocation, $tempFile);
        if (!$copied) {
            throw new DaemonException('Failed to copy file to temp file');
        }

        // For now make said file world-readable (for clamav host)
        if (!chmod($tempFile, 0644)) {
            throw new DaemonException('Failed to change permissions on temp file');
        }

        // And finally scan
        $response = $this->executeCommand('SCAN ' . $tempFile, 60);
        if (str_ends_with($response, 'OK')) {
            return;
        }
        throw new FileException(
            sprintf('Virus scanner received response "%s": presuming virus', $response)
        );
    }

    /**
     * @inheritdoc
     */
    public function checkExists(): string
    {
        try {
            $response = $this->executeCommand('PING', 5);
        } catch (DaemonException) {
            return self::STATUS_UNAVAILABLE;
        }
        if ($response === 'PONG') {
            return self::STATUS_ONLINE;
        }
        return self::STATUS_OFFLINE;
    }

    /**
     * Attempt to send a command to the socket.
     *
     * @param string $command The command to send.
     * @param int    $timeout How many seconds to wait for a response.
     * @return string The response to the command.
     * @throws DaemonException if the command failed to run.
     */
    private function executeCommand(string $command, int $timeout): string
    {
        if (!extension_loaded('sockets')) {
            throw new DaemonException('PHP sockets extension is not loaded');
        }
        if (!isset($this->clamDaemonHost, $this->clamDaemonPort)) {
            throw new DaemonException('ClamAV daemon host and port are not configured');
        }

        // Can the port be accessed?
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($socket, $this->clamDaemonHost, $this->clamDaemonPort);

        // Send the command, and wait for a response
        socket_write($socket, self::COMMAND_PREFIX . $command . self::DELIMITER);
        $response    = '';
        $timeoutTime = microtime(true) + $timeout;
        while (microtime(true) < $timeoutTime) {
            $response .= socket_read($socket, 1024);

            // If last byte is the delimiter: we have a fully realised response.
            if (str_ends_with($response, self::DELIMITER)) {
                socket_close($socket);
                return rtrim($response, self::DELIMITER);
            }
            usleep(100000);
        }
        socket_close($socket);
        return $response;
    }
}
