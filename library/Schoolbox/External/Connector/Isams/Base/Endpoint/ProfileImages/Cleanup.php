<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\ProfileImages;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use SensitiveParameter;

class Cleanup implements IStrategy
{
    public function __construct(
        private LoggerInterface $log,
        #[SensitiveParameter] private string $key,
        private string $zipDir
    ) {
    }

    /**
     * @inheritDoc
     */
    public function import(): void
    {
        // if batch API exists, no photos to clean up
        $credentials = (new DSN())->decode($this->key);
        if (trim($credentials['modules'][DSN::MODULE_RESTAPIHOST]['key'] ?? '') === '') {
            /* Only try to clean up photos if we actually tried to import them
             * Otherwise leave this place untouched
             */
            $this->cleanup();

            $this->log->info("Removed residual photos and zip files from isams cache dir.");
        }
    }

    /**
     * Removes all the photo-related files from the isams cache dir
     *
     * @return void
     */
    private function cleanup(): void
    {
        $dirhandle = opendir($this->zipDir);

        while ($file = readdir($dirhandle)) {
            if ($file !== '.' && $file !== '..') {
                $source = "{$this->zipDir}/{$file}";

                unlink($source);
            }
        }
    }
}
