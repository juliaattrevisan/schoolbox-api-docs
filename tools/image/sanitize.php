#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use League\Flysystem\FilesystemInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * This tool is to sanitize svg files
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
        $svgFiles      = $storageMapper->findByColumn('ext', 'svg');

        /** @var FilesystemInterface $storageFilesystem */
        $storageFilesystem = $this->getDi()->get('filesystem.storage');

        /** @var Storage $svgFile */
        foreach ($svgFiles as $svgFile) {
            $path   = FilesystemProxy::getPathFromHash($svgFile->getHash());
            $result = $mountManager->sanitize($svgFile, $storageFilesystem, $path);
            if (isset($result)) {
                $this->getLogger()->debug(($this->perform() ? 'Sanitized: ' : 'Will be sanitized: ') . $svgFile->getHash());
                if ($this->perform()) {
                    // TODO write the new content
                }
            } else {
                $this->getLogger()->debug(($this->perform() ? 'Skipped: ' : 'Will be skipped: ') . $svgFile->getHash());
            }
        }
    }
};

(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
