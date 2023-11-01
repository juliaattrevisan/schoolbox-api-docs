<?php

namespace Schoolbox\Resource\Storage\Virus;

use Schoolbox\File\Exception as FileException;

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class Scanner
{

    /**
     * TODO: Possible alerts (alert-encrypted, alert-macros)
     * TODO: DOS protection (max-scantime=2min, max-filesize=25MB)
     */
    private const CLAMSCAN_COMMAND = 'clamdscan --fdpass --move=%s %s';

    private const VIRUS_QUARANTINE = '/quarantine/';

    private string $quarantine;
    public function __construct()
    {
        $this->quarantine = sys_get_temp_dir();
    }

    /**
     * Will scan a file for virus and throw and exception if one is found
     * The file will also be moved to the quarantine directory
     *
     * @param $filelocation Path to temporary file on disk to be scanned
     * @return void
     * @throws FileException
     */
    public function scan($filelocation): void
    {
        $output = '';
        $resultCode = 0;

        exec(sprintf(self::CLAMSCAN_COMMAND, $this->quarantine, $filelocation), $output, $resultCode);

        if ($resultCode > 0) {
            throw new FileException('A virus has been detected and this file has been quarantined');
        }
    }
}
