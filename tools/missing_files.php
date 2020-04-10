<?php
/*
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2020, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\IConfigLoader;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

$app = new class extends CliTool\App {
    public function run(): void
    {
        /** @var IConfigLoader $config */
        $config = $this->getDi()->get('config');

        $storageMapper = $this->getDi()->get('mapperFactory')->get(\Schoolbox\Resource\Storage\Mapper::class);

        $okCount = 0;
        $errorCount = 0;


        $storageDir    = rtrim($config->get('storage_dir'), DIRECTORY_SEPARATOR);

        // Retrieve and iterate all files referenced by `storage` table in database
        $files    = $storageMapper->findAll();
        $countAll = $files->count();
        foreach ($files as $file) {
            /**
             * @var  \Schoolbox\Resource\Storage\Model $file
             */

            $filepath     = "{$storageDir}/{$file->getPath()}";
            if(file_exists($filepath)) {
                if(filesize($filepath) === $file->getSize()) {
                    $okCount++;
                    $this->getLogger()->info("File OK $okCount/$errorCount/$countAll  {$file->getHash()}, '{$file->getBasename()}'");

                }
                else {
                    $errorCount++;
                    $this->getLogger()->error("File invalid size $okCount/$errorCount/$countAll  {$file->getHash()}, '{$file->getBasename()}'");
                }
            }
            else {
                $errorCount++;
                $this->getLogger()->error("File does not exist $okCount/$errorCount/$countAll  {$file->getHash()}, '{$file->getBasename()}'");
            }

        }
        $this->getLogger()->notice("Completed scanning $countAll: $errorCount files missing, $okCount files found");

    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runapp();

