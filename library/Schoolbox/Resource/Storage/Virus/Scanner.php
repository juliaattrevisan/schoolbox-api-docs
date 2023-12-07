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
     */
    private const CLAMSCAN_COMMAND = 'clamdscan';

    /**
     * We utilise 'fdpass' so that file descriptor is passed to avoid issues with permissions in clamd
     * If the file is a virus, we use the 'move' parameter to specific quarantine location
     */
    private const  CLAMSCAN_ARGS = '--fdpass --move=%s %s';

    /**
     * This is the quarantine location, it sits under the system temp directory and will be automatically created
     */
    private const VIRUS_QUARANTINE = '/quarantine/';

    private string $quarantine;
    public function __construct(private Filesystem $tempFileSystem)
    {


        if (!$this->tempFileSystem->directoryExists(self::VIRUS_QUARANTINE)) {
            $this->tempFileSystem->createDirectory(self::VIRUS_QUARANTINE);
        }

        $this->quarantine = sys_get_temp_dir() . self::VIRUS_QUARANTINE;
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

        exec(sprintf(self::CLAMSCAN_COMMAND . ' ' . self::CLAMSCAN_ARGS, $this->quarantine, $filelocation), $output, $resultCode);

        // Virus scanner not available, just continue without it
        if (127 === $resultCode) {
            return;
        }

        /* Some random error, but we found that it might be caused by
         * clamd not currently running. What it should do in this case
         * is yet to be decided
         */
        if (2 === $resultCode) {
            /* clamd might not be running.
             *
             * You can find more info about this here
             * https://www.linuxquestions.org/questions/linux-server-73/can%27t-connect-to-unix-socket-var-run-clamav-clamd-ctl-connection-refused-856847/
             *
             * Tets you see if clamd is running:
             *     ps -ef|grep clamd
             *
             * lets you restart it:
             *     sudo /etc/init.d/clamav-daemon start
             *
             * This error message surfaces on the frontend.
             */
            throw new DaemonException('Error in virus detection service.');
        }

        if ($resultCode > 0) {
            throw new FileException('A virus has been detected and this file has been quarantined');
        }
    }
}
