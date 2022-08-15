#!/usr/bin/php
<?php

/**
 * Usage: ./cleanup.php [-p] [-vvv]
 *
 * A tool that will remove all UPS logs older than 1 month
 *
 *  -p          perform: *without* this flag, will only do a dry run
 *  -v[v[v]]    verbosity: how much activity will be logged
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Monolog\Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\RelationalDelete\RelationalDelete;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new class extends CliTool\App
{
    public function run(): void
    {
        $removeDate = Alaress_Date_Format::newFromTimestamp(strtotime('-1 month'));
        $logMapper = Schoolbox\Sync\Log\Mapper::getInstance();
        /** @var MapperFactory $mapperFactory */
        $mapperFactory  = $this->getDi()->get('mapperFactory');
        $storageDeleter = new RelationalDelete($this->getLogger(), $mapperFactory->get(Storage\Mapper::class));


        $loggerMessageSufix = !$this->perform() ? '[Dry run mode]' : '[Delete mode]';

        $this->getLogger()->info('Starting UPS log cleanup on: ' . $loggerMessageSufix);
        $this->getLogger()->info('Removing all UPS logs older than: ' . $removeDate->getDatabase());

        $count = 0;
        $size = 0;

        $pb = $logMapper->getPredicateBuilder();
        $pb->lessThan('created_at', $removeDate->getDatabase());
        $removeLogs = $logMapper->findByPredicates($pb);


        foreach ($removeLogs as $log) {
            /** @var Schoolbox\Sync\Log\Model $log */
            $this->getLogger()->info('Removing log file created on: ' . $log->getCreatedAt()->getDatabase());
            $count++;
            $size += $log->getStorage()->getSize();
            $storageDeleter->remove($log->getStorage()->getHash(), !$this->perform());
            if ($this->perform()) {
                $logMapper->delete($log);
            }
        }
        $this->getLogger()->info(sprintf('Removed %d UPS log files, recovering %s of disk space', $count, Alaress_Byte_Format::bytesToString($size)));
        exit(0);
    }
}, [CliTool\Kernel::OPTION_LEVEL => Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->runApp();
