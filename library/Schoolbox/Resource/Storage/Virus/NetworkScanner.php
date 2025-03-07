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
    /**
     * Prefix for commands.
     *
     * This implementation prefixes commands with a `z`, so that a null byte is
     * used as the delimiter for both commands and their responses.
     */
    private const COMMAND_PREFIX = 'z';

    /**
     * Null bytes are used as the delimiter for commands and responses.
     */
    private const DELIMITER = "\0";

    /**
     * Constructor
     *
     * @param string $sharedDirectory     The shared directory on this host to copy files to.
     * @param string $clamDaemonHost      The hostname the ClamAV daemon is available on.
     * @param int    $clamDaemonPort      The port the ClamAV daemon is listening on.
     * @param string $clamDaemonDirectory The directory on the ClamAV host from which it will read files to scan.
     *                                    It's assumed that this and `$sharedDirectory` are a shared mount.
     */
    public function __construct(
        private string $sharedDirectory,
        private string $clamDaemonHost,
        private int $clamDaemonPort,
        private string $clamDaemonDirectory,
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
        $response = $this->executeCommand('SCAN ' . $this->clamDaemonDirectory . '/' . basename($tempFile), 60);
        if (str_ends_with($response, 'OK')) {
            return;
        }
        if (str_ends_with($response, 'FOUND')) {
            throw new FileException(
                sprintf('Virus scanner received response "%s": looks like a virus', $response)
            );
        }
        throw new DaemonException(
            sprintf('Virus scanner received unknown response "%s"', $response)
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
