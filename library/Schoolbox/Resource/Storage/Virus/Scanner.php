<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Virus;

use Schoolbox\File\Exception as FileException;
use League\Flysystem\Filesystem;

/**
 * Virus scanner implementation, which uses a locally available `clamdscan`
 * instance for scanning.
 */
class Scanner implements IScanner
{
    /**
     * We use clamdscan to pass file to already running daemon of clamd
     *
     * We utilise 'fdpass' so that file descriptor is passed to avoid issues with permissions in clamd
     * If the file is a virus, we use the 'move' parameter to specific quarantine location
     */
    private const CLAMSCAN_COMMAND = 'clamdscan --fdpass --move=%s %s';

    /**
     * Ask clamd to scan its own config file as a test
     */
    private const TEST_CLAMSCAN_COMMAND = 'clamdscan --fdpass /etc/clamav/clamd.conf';

    /**
     * Constructor
     *
     * @param Filesystem $tempFileSystem
     * @throws \League\Flysystem\FilesystemException
     * @throws \League\Flysystem\UnableToCheckExistence
     * @throws \League\Flysystem\UnableToCreateDirectory
     */
    public function __construct(private Filesystem $tempFileSystem)
    {
        if (!$this->tempFileSystem->directoryExists(self::VIRUS_QUARANTINE)) {
            $this->tempFileSystem->createDirectory(self::VIRUS_QUARANTINE);
        }
    }


    /**
     * @inheritdoc
     */
    public function scan(string $fileLocation): void
    {
        $output = [];
        $resultCode = null;

        exec(sprintf(
            // Note '2>&1' redirects the output intended for stderr to stdout (so it will end up in the $output variable).
            // Without this redirection, a message like 'sh: 1: clamdscan: not found' can appear in unwanted places.
            // The message does not give any more information than the 127 return code.
            self::CLAMSCAN_COMMAND . ' 2>&1',
            sys_get_temp_dir() . self::VIRUS_QUARANTINE,
            $fileLocation,
        ), $output, $resultCode);

        // From clamdscan's man page:
        // RETURN CODES
        // 0 : No virus found.
        // 1 : Virus(es) found.
        // 2 : An error occurred.
        // (127 is the standard response code for when a command is not found)

        // Virus scanner not available, just continue without it
        if (127 === $resultCode) {
            return;
        }

        /* clamd not currently running or accessible. Might have died due to OOM or not installed properly  */
        if (2 === $resultCode) {
            /* clamd might not be running. skip scan */
            return;
        }

        if ($resultCode > 0) {
            throw new FileException('A virus has been detected and this file has been quarantined.');
        }
    }

    /**
     * @inheritdoc
     */
    public function checkExists(): string
    {
        $output = [];
        $resultCode = null;

        // Note '2>&1' redirects the output intended for stderr to stdout (so it will end up in the $output variable).
        // Without this redirection, a message like 'sh: 1: clamdscan: not found' can appear in unwanted places.
        // The message does not give any more information than the 127 return code.
        exec(self::TEST_CLAMSCAN_COMMAND . ' 2>&1', $output, $resultCode);

        // From clamdscan's man page:
        // RETURN CODES
        // 0 : No virus found.
        // 1 : Virus(es) found.
        // 2 : An error occurred.
        // (127 is the standard response code for when a command is not found)

        // Virus scanner not available
        if (127 === $resultCode) {
            return self::STATUS_UNAVAILABLE;
        }

        /* clamd not currently running or accessible. Might have died due to OOM or not installed properly  */
        if (2 === $resultCode) {
            return self::STATUS_OFFLINE;
        }

        return self::STATUS_ONLINE;
    }
}
