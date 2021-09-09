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
        if ($this->perform()) {
            $this->getLogger()->info('Running in perform mode (-p): changes will be written to filesystem');
        } else {
            $this->getLogger()->info('Running in dry-run mode: use -p to write changes to filesystem');
        }
        $storageMapper = new StorageMapper();
        $mountManager  = $storageMapper->getManager();
        $exifFiles     = $storageMapper->findByColumn('ext', ['jpg','jpeg','jpe','tif','tiff']);
        /** @var Storage $exifFile */
        foreach ($exifFiles as $exifFile) {
            $result = $mountManager->sanitize($exifFile, $mountManager::FS_STORAGE, $exifFile->getPath(), !$this->perform());
            if ($result) {
                $this->getLogger()->debug(($this->perform() ? 'Sanitized: ' : 'Will be sanitized: ') . $exifFile->getHash());
            } else {
                $this->getLogger()->debug(($this->perform() ? 'Skipped: ' : 'Will be skipped: ') . $exifFile->getHash());
            }
        }
    }
};

(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
