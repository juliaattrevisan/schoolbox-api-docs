<?php

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Virus;

use Schoolbox\File\Exception as FileException;
use League\Flysystem\Filesystem;

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class Scanner
{
    /**
     * We use clamdscan to pass file to already running daemon of clamd
     *
     * We utilise 'fdpass' so that file descriptor is passed to avoid issues with permissions in clamd
     * If the file is a virus, we use the 'move' parameter to specific quarantine location
     */
    private const CLAMSCAN_COMMAND = 'clamdscan --fdpass --move=%s %s';


    /**
     * This is the quarantine location, it sits under the system temp directory and will be automatically created
     */
    private const VIRUS_QUARANTINE = '/quarantine/';


    /**
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
     * Will scan a file for virus and throw and exception if one is found
     * The file will also be moved to the quarantine directory
     *
     * @param string $filelocation Path to temporary file on disk to be scanned
     * @return void
     * @throws DaemonException
     * @throws FileException
     */
    public function scan(string $filelocation): void
    {
        $output = '';
        $resultCode = 0;

        exec(sprintf(
            self::CLAMSCAN_COMMAND,
            sys_get_temp_dir() . self::VIRUS_QUARANTINE,
            $filelocation
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
}
