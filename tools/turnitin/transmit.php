#!/usr/bin/php
<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Component;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';

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
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * @inheritdoc
     */
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

Find every Pending turnitin report in turnitin_report and set up the turnitin Group, Submission
and upload the file's contents. Used periodically to kick everything up to turnit in for another
async process to do its work.

Usage: transmit.php

HELP;
            exit;
        }

        $this->getLogger()->info('Script commencing.');

        /* We need this api
         */
        $api = new Turnitin\Api($this->getConfig()->get('turnitin_api_host'), $this->getConfig()->get('turnitin_api_key'));

        /* Set up a record processor
         */
        $processor = (new Turnitin\Process\File(
            $api,
            Turnitin\Mapper::getInstance(),
            UserMapper::getInstance(),
            Component\SubmissionBoxMapper::getInstance(),
            SingleFile\Mapper::getInstance()
        ));

        /* Find all the Pending records
         */
        $models = Turnitin\Mapper::getInstance()->findByColumn('status', Turnitin\Model::STATUS_SETUP_PENDING);

        /* Shank them all up
         */
        foreach ($models as $model) {
            $processor->process($model);
        }

        $this->getLogger()->info('Done.');
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
