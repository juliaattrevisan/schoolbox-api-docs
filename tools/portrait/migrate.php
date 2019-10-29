#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Admin\Import\Photos\MigrateToStorage;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\Mapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Migrate portraits on the local filesystem into Storage
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Migrate portraits from filesystem portrait_dir into Storage and associate them
with schoolbox user accounts

Usage: migrate.php


HELP;
            exit;
        }

        /* Migrate into storage
         */
        (new MigrateToStorage(
            $this->getConfig()->get('portrait_dir'),
            Database::getInstance(),
            Mapper::getInstance(),
            $this->getLogger(),
            $this->perform()
        ))->migrate();
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
