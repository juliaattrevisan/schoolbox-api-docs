<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Images;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use SensitiveParameter;

class Cleanup implements IStrategy
{
    private string $cacheDir;


    /**
     * Be circumspect with this, because it runs regardless of which photo downloader
     * (batch or rest))
     * @param LoggerInterface $log
     * @param string $key
     * @param string $zipDir
     */
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
        if (trim($credentials['modules'][DSN::MODULE_RESTAPI]['host'] ?? '') === '') {
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
        /* FIXME Arguably this should be passed in through the Factorys at the uppermost levels
         *  It's too easy to neglect the ISAMS_CACHE_DIR subfolder
         */
        $cacheDir = $this->zipDir . '/' . BatchPhotoDownloader::ISAMS_CACHE_DIR;

        $dirhandle = opendir($cacheDir);

        while ($file = readdir($dirhandle)) {
            /* There might not actually be any profile pictures here
             */
            if ($file !== '.' && $file !== '..' && $file !== '') {
                $source = "{$cacheDir}/{$file}";

                $this->log->debug(sprintf(
                    'Unlinking zip remnants: %s',
                    $source
                ));

                unlink($source);
            }
        }
    }
}
