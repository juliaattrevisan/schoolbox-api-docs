#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Admin\Import\Photos\MigrateToStorage;
use Schoolbox\Application\CliTool;
use Schoolbox\Database\Factory;
use Schoolbox\Resource\Storage\Mapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Migrate portraits on the local filesystem into Storage
 */
$app = new class extends CliTool\BaseApp
{
    /** @inheritDoc */
    public function getRequiredProcessUser(): string
    {
        return 'www-data';
    }

    protected function getName(): string
    {
        return 'migrate';
    }

    protected function getDescription(): string
    {
        return 'Migrate portraits from filesystem portrait_dir into Storage and associate them with schoolbox user accounts.';
    }

    protected function setOptions(): self
    {
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return false;
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        /* Migrate into storage
         */
        (new MigrateToStorage(
            $this->getConfig()->get('portrait_dir'),
            Factory::getInstanceInternal(),
            Mapper::getInstance(),
            $this->getLogger(),
            true
        ))->migrate();
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
