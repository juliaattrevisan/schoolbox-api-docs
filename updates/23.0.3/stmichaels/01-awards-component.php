<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
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

Makes the St Michaels Student Awards component only available to St Michaels.

FOr everyone else,
    enabled: 0
    visible: 'false'

There is no perform mode; it always persists changes.

Usage: 01-awards-component.php


HELP;
            return;
        }

        $siteLocation = $this->getConfig()->get(ConfigMapper::NAME_SITE_SCHOOL_CODE);

        /* Hide the St Michaels Student Awards Component from everyone except St Michaels
         * For this school, retain whatever setting they're currently using (leave unchanged)
         */
        if ('smgs' !== strtolower($siteLocation)) {
            $this->getLogger()->debug('Hiding St Michaels Student Awards Component');

            $query = "
                UPDATE component_definition
                SET enabled = 0,
                visible = 'false'
                WHERE class = 'Component_Dashboard_StMichaelsAwardsController'
                LIMIT 1
            ";

            $this->getDatabaseInternal()->executeQuery($query);
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
