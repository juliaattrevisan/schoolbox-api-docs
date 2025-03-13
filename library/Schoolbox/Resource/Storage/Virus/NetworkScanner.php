<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Virus;

use League\Flysystem\Filesystem;
use Schoolbox\File\Exception as FileException;
use SplFileObject;
use Throwable;

/**
 * Virus scanner implementation, which uses a network-available `clamd` daemon.
 *
 * @see https://docs.clamav.net/manual/Usage/Scanning.html#daemon
 */
class NetworkScanner implements IScanner
{
    /**
     * The default value for `$this->maxFileSize` (20 MiB).
     */
    public const DEFAULT_MAX_FILE_SIZE = 1024 * 1024 * 20;

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
     * The size (in bytes) of each chunk to write to `clamd`, when streaming a
     * file to be scanned.
     */
    private const CHUNK_SIZE_WRITE = 8192;

    /**
     * The size (in bytes) of each chunk to read from `clamd`, when receiving a
     * response.
     */
    private const CHUNK_SIZE_READ = 8192;

    /**
     * Constructor
     *
     * @param Filesystem $tempFileSystem The temporary file system: quarantined files are moved to
     *                                   `IScanner::VIRUS_QUARANTINE` in this file system.
     * @param string     $clamDaemonHost The hostname the ClamAV daemon is available on.
     * @param int        $clamDaemonPort The port the ClamAV daemon is listening on.
     * @param int        $maxFileSize    Don't attempt to scan files larger than this: defaults to 20MiB.
     */
    public function __construct(
        private Filesystem $tempFileSystem,
        private string $clamDaemonHost,
        private int $clamDaemonPort,
        private int $maxFileSize = self::DEFAULT_MAX_FILE_SIZE,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function scan(string $fileLocation): void
    {
        // Stream file to socket
        try {
            $file = new SplFileObject($fileLocation, 'r');
        } catch (Throwable $e) {
            throw new DaemonException('File could not be opened for reading', 0, $e);
        }

        // Too large to scan? Don't attempt to scan it.
        if ($file->getSize() > $this->maxFileSize) {
            return;
        }

        $response = $this->executeCommand('INSTREAM', 60, $file);
        if (str_ends_with($response, 'OK')) {
            return;
        }
        if (str_ends_with($response, 'FOUND')) {
            // If found: quarantine (this mimics `clamscand --move`)
            unset($file);
            $this->tempFileSystem->writeStream(
                self::VIRUS_QUARANTINE . '/' . basename($fileLocation),
                fopen($fileLocation, 'r'),
            );
            unlink($fileLocation);
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
     * @param string             $command The command to send.
     * @param int                $timeout How many seconds to wait for a response.
     * @param SplFileObject|null $file    If provided: stream this file to the socket.
     * @return string The response to the command.
     * @throws DaemonException if the command failed to run.
     */
    private function executeCommand(string $command, int $timeout, ?SplFileObject $file = null): string
    {
        if (!extension_loaded('sockets')) {
            throw new DaemonException('PHP sockets extension is not loaded');
        }

        // Can the port be accessed?
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($socket, $this->clamDaemonHost, $this->clamDaemonPort);

        // Send the command, streaming $file if set, and wait for a response
        socket_write($socket, self::COMMAND_PREFIX . $command . self::DELIMITER);
        if ($file) {
            while (!$file->eof()) {
                $bytes = $file->fread(self::CHUNK_SIZE_WRITE);
                if ($bytes === false) {
                    break;
                }
                socket_write($socket, pack('N', strlen($bytes)));
                socket_write($socket, $bytes);
            }
            socket_write($socket, pack('N', 0));
        }

        // Read the response from the socket
        $response    = '';
        $timeoutTime = microtime(true) + $timeout;
        while (microtime(true) < $timeoutTime) {
            $response .= socket_read($socket, self::CHUNK_SIZE_READ);

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
