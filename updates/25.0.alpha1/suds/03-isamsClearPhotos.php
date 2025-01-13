<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App
{
    /**
     * Run the cli tool
     */
    public function run(): void
    {
        /**
         * only run if we are suds isams
         */
        if (
            $this->getConfig()->get(ConfigMapper::NAME_SCHOOLDATA_IMPORT_DATA_SOURCE) === \External\IConnector::EXTERNAL_TYPE_ISAMS
            &&
            $this->getConfig()->get(ConfigMapper::NAME_EXTERNAL_TYPE) === \External\IConnector::EXTERNAL_TYPE_SCHOOLBOX
        ) {
            $this->getDatabaseInternal()->query("UPDATE sis_user_profile SET portrait_hash = NULL");
        }
    }


    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
