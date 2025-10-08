<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Virus;

use Alaress_Byte_Format;
use League\Flysystem\Filesystem;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

/**
 * Factory for `IScanner` implementations.
 */
class ScannerFactory
{
    /**
     * Constructor
     *
     * @param IConfigLoader $config         Configuration
     * @param Filesystem    $tempFileSystem Temporary file system: scanners should move quarantined files here.
     */
    public function __construct(
        private IConfigLoader $config,
        private Filesystem $tempFileSystem,
    ) {
    }

    /**
     * Build the scanner.
     *
     * @return IScanner
     */
    public function build(): IScanner
    {
        $clamDaemonHost        = $this->config->get(ConfigMapper::NAME_CLAMD_HOST);
        $clamDaemonPort        = $this->config->get(ConfigMapper::NAME_CLAMD_PORT);
        $clamDaemonMaxFileSize = $this->config->get(ConfigMapper::NAME_CLAMD_MAX_FILE_SIZE);
        if ($clamDaemonHost && $clamDaemonPort) {
            if ($clamDaemonMaxFileSize) {
                $clamDaemonMaxFileSize = Alaress_Byte_Format::stringToBytes($clamDaemonMaxFileSize);
            }
            $clamDaemonMaxFileSize = $clamDaemonMaxFileSize ?: NetworkScanner::DEFAULT_MAX_FILE_SIZE;
            return new NetworkScanner(
                $this->tempFileSystem,
                (string)$clamDaemonHost,
                (int)$clamDaemonPort,
                $clamDaemonMaxFileSize,
            );
        }

        return new Scanner($this->tempFileSystem);
    }
}
