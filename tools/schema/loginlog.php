#!/usr/bin/php
<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

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

This periodically prunes the login_log table. Anything older than
two years from the time of running is deleted permanently.

Planning mode shows you the stuff that would get pruned.

Perform mode actually prunes; this will silently delete the candidate records.


Usage: loginlog.php


HELP;
            exit;
        }

        if (!$this->perform()) {
            $this->getLogger()->info('Searching for records to prune.');

            /* Planning mode
             */
            $statement = $this->getDatabaseInternal()->executeQuery("
                SELECT *
                FROM login_log
                WHERE login_log.`date` < NOW() - INTERVAL 2 YEAR
            ");

            while ($row = $statement->fetch()) {
                $this->getLogger()->info(sprintf(
                    '%s: %s %s',
                    $row['id'],
                    $row['date'],
                    $row['username']
                ));
            }

            $this->getLogger()->info('Planning finished.');
        } else {
            /* Perform mode
             */
            $this->getDatabaseInternal()->executeQuery("
                DELETE
                FROM login_log
                WHERE login_log.`date` < NOW() - INTERVAL 2 YEAR
            ");
        }
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
        return null;
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
