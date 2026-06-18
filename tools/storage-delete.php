#!/usr/bin/env php
<?php

/**
 * Usage: ./gc-storage.php [--hash] [-p] [-vvv]
 *
 * A tool that will perform a removal of file in storage by the provided hash
 *
 *  -p          perform: *without* this flag, GC will only do a dry run
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

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new CliTool\Kernel(new class extends CliTool\BaseApp
{
    private const HASH = 'hash:';

    private $hash;

    protected function getName(): string
    {
        return 'storage-delete';
    }

    protected function getDescription(): string
    {
        return 'Removes a file from storage by the provided hash.';
    }

    protected function setOptions(): self
    {
        $this->addOption(null, self::HASH, 'The hash of the file to delete from storage');
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return true;
    }


    public function run(): void
    {
        $this->hash = $this->getOpt(self::HASH);

        $loggerMessageSufix = !$this->perform() ? '[Dry run mode]' : '[Delete mode]';

        $this->getLogger()->info('Starting storage delete tool on: ' . $loggerMessageSufix);


        if (!$this->hash) {
            $this->getLogger()->error('[ERROR]: Hash cannot be empty...');
            die();
        }

        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        (new RelationalDelete($this->getLogger(), $mapperFactory->get(Storage\Mapper::class)))->remove($this->hash, !$this->perform());

        exit(0);
    }
}, [CliTool\Kernel::OPTION_LEVEL => Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->runApp();
