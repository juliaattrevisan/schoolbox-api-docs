#!/usr/bin/php
<?php

/*
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2020, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage;

require_once __DIR__ . '/../conf/core/require.php';
$app = new class extends CliTool\App {
    public function run(): void
    {
        /** @var Storage\Mapper $storageMapper */
        $storageMapper = $this->getDi()->get('mapperFactory')->get(Storage\Mapper::class);

        /** @var Storage\FilesystemProxy $filesystemProxy */
        $filesystemProxy = $this->getDi()->get('filesystem.storageProxy');
        $okCount = 0;
        $errorCount = 0;

        // Retrieve and iterate all files referenced by `storage` table in database
        $files    = $storageMapper->findAll();
        $countAll = $files->count();
        foreach ($files as $file) {
            if ($filesystemProxy->fileExists($file)) {
                if ($file->getFilesystem()->getSize($file->getPath()) === $file->getSize()) {
                    $okCount++;
                    $this->getLogger()->info("File OK $okCount/$errorCount/$countAll  {$file->getHash()}, '{$file->getBasename()}'");
                } else {
                    $errorCount++;
                    $this->getLogger()->error("File invalid size $okCount/$errorCount/$countAll  {$file->getHash()}, '{$file->getBasename()}'");
                }
            } else {
                $errorCount++;
                $this->getLogger()->error("File does not exist $okCount/$errorCount/$countAll  {$file->getHash()}, '{$file->getBasename()}'");
            }
        }
        $this->getLogger()->notice("Completed scanning $countAll: $errorCount files missing, $okCount files found");
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runapp();
