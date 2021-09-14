#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * This tool is to strip location data from exif to protect peoples privacy
 * Use the -p option to commit the changes to disk
 */
$app = new class extends CliTool\App {
    /* @var array */

    public function run(): void
    {
        if (!$this->getConfig()->get('remove_image_location_data')) {
            $this->getLogger()->info('Remove Image Location Data is disabled in config; exiting');
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        if ($this->perform()) {
            $this->getLogger()->info('Running in perform mode (-p): changes will be written to filesystem');
        } else {
            $this->getLogger()->info('Running in dry-run mode: use -p to write changes to filesystem');
        }
        $storageMapper = new StorageMapper();
        $mountManager  = $storageMapper->getManager();
        $exifFiles     = $storageMapper->findByColumn('allowedFileTypes.mime', ['image/jpeg', 'image/tiff']);

        /** @var Storage $exifFile */
        foreach ($exifFiles as $exifFile) {
            $result = $mountManager->sanitize($exifFile, !$this->perform());
            if ($result) {
                $this->getLogger()->debug(($this->perform() ? 'Sanitized: ' : 'Will be sanitized: ') . "{$exifFile->getFilename()} ({$exifFile->getHash()})");
            } else {
                $this->getLogger()->debug(($this->perform() ? 'Skipped: ' : 'Will be skipped: ') . "{$exifFile->getFilename()} ({$exifFile->getHash()})");
            }
        }
    }
};

(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
