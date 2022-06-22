#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use League\Flysystem\FilesystemAdapter;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Resource\Storage\Sanitizer;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * This tool is to sanitize svg files
 */
$app = new class extends CliTool\App {
    /* @var array */

    /**
     * This process needs to write to storage, thus it must run as `www-data`.
     */
    public function getRequiredProcessUser(): ?string
    {
        return 'www-data';
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        if ($this->perform()) {
            $this->getLogger()->info('Running in perform mode (-p): changes will be written to filesystem');
        } else {
            $this->getLogger()->info('Running in dry-run mode: use -p to write changes to filesystem');
        }
        $storageMapper = new StorageMapper();
        $svgFiles      = $storageMapper->findByColumn('ext', 'svg');

        /** @var FilesystemAdapter $storageFilesystem */
        $storageFilesystem = $this->getDi()->get('filesystem.storage');

        /** @var Sanitizer\ISanitizer $sanitizer */
        $sanitizer = $this->getDi()->get('storage.sanitizer');
        $helper    = new Sanitizer\Helper($sanitizer);

        /** @var Storage $svgFile */
        foreach ($svgFiles as $svgFile) {
            $path   = FilesystemProxy::getPathFromHash($svgFile->getHash());
            $result = $helper->sanitize($storageFilesystem, $path, $svgFile->getMime());
            if (isset($result)) {
                $this->getLogger()->debug(($this->perform() ? 'Sanitized: ' : 'Will be sanitized: ') . $svgFile->getHash());
                if ($this->perform()) {
                    // Write the sanitised content to the existing file path.
                    // This violates an apparent rule that the storage file's
                    // path should match its hash, but it's the only viable way
                    // so far to ensure the sanitised content is accessible via
                    // its original hash.
                    $storageFilesystem->write($path, $result);
                }
            } else {
                $this->getLogger()->debug(($this->perform() ? 'Skipped: ' : 'Will be skipped: ') . $svgFile->getHash());
            }
        }
    }
};

(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
