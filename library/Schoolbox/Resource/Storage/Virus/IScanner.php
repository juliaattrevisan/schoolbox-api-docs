<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Virus;

use Schoolbox\File\Exception as FileException;

/**
 * Interface for virus scanners.
 */
interface IScanner
{
    /**
     * This is the quarantine location, it sits under the system temp directory and will be automatically created
     */
    public const VIRUS_QUARANTINE = '/quarantine/';

    /**
     * Scans a file for viruses, and throw an exception if one is found.
     *
     * The file will also be moved to the `self::VIRUS_QUARANTINE` directory
     * under the temp directory, if it contains a virus.
     *
     * @param string $fileLocation Path to file on disk to be scanned.
     * @return void
     * @throws FileException if the file at `$filelocation` contains a virus
     */
    public function scan(string $fileLocation): void;

    /**
     * Does this virus scanner exist?
     *
     * @return string
     */
    public function checkExists(): string;
}
