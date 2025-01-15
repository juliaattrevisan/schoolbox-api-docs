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
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use SensitiveParameter;

class Prepare implements IStrategy
{
    public function __construct(
        private LoggerInterface $log,
        private $host,
        #[SensitiveParameter] private string $key,
        private string $zipDir,
        private ValidStrats $validStrats
    ) {
    }

    /**
     * @inheritDoc
     */
    public function import(): void
    {
        // if batch API exists, use that for photos, else fallback to batchxml thumbnails
        $credentials = (new DSN())->decode($this->key);

        if (trim($credentials['modules'][DSN::MODULE_RESTAPI]['host'] ?? '') === '') {
            $photoDownloader = new BatchPhotoDownloader($this->host, $credentials, $this->zipDir);

            if ($this->validStrats->isStudentStratEnabled()) {
                /* Download a zip containing everyone's profile pictures, and extract them
                * It uses isams internal ids, not external ids
                */
                $this->log->info('Downloading student profile photos (this might take a while)');

                $photoDownloader->downloadStudentPhotos();
            }

            if ($this->validStrats->isStaffStratEnabled()) {
                /* Download a zip containing everyone's profile pictures, and extract them
                 * It uses isams internal ids, not external ids
                 */
                $this->log->info('Downloading staff profile photos (this might take a while)');

                $photoDownloader->downloadStaffPhotos();
            }
        }
    }
}
