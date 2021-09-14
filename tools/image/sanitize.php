#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;
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
        /** @var Storage $svgFile */
        foreach ($svgFiles as $svgFile) {
            $result = $mountManager->sanitize($svgFile, !$this->perform());
            if ($result) {
                $this->getLogger()->debug(($this->perform() ? 'Sanitized: ' : 'Will be sanitized: ') . $svgFile->getHash());
            } else {
                $this->getLogger()->debug(($this->perform() ? 'Skipped: ' : 'Will be skipped: ') . $svgFile->getHash());
            }
        }
    }
};

(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
