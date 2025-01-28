<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
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
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const MY_TOOL_LOCK_NAME = 'learnosity_activity_restore';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 60;


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --reference
     *
     * An alphanumeric string corresponding to a Learnosity activity reference
     */
    private const OPT_LONG_REFERENCE = 'reference:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_REFERENCE,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Learnosity Activity Restore

In Planning mode, this will tell you about all the active schoolbox quizzes and their Learnosity
counterparts, along with the Learnosity status, so you can find ones that have been somehow
inadvertently archived in Learnosity.

Quizzes that have yet to finish will appear in this list. Already-expired quizzes will not.
Specify one(s) to restore with the cli arg(s) --reference and/or --restore-all
 

Usage: restore.php [--reference] 
        --reference      A Learnosity activity reference to restore
        --restore-all    Restores all Learnosity activities to active status            

HELP;
            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::MY_TOOL_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        /* FIXME Main algorithm goes here
         */
        $this->getLogger()->info('Script commencing.');

        /* TODO Implement your iterations here, refreshing lock each time
         *  This will grant you LOCK_TTL more seconds to do another iteration,
         *  until the script unlocks itself
         */
        try {
            for ($i = 0; $i < 10; $i++) {
                $this->refreshLock($lock);

                /* Your logic goes here
                 */
            }
        } catch (\Alaress\Lock\LockRefreshException $e) {
            /* Lock TTL expired; just leave politely
             */
            return;
        }


        /* Announce conclusion
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        /* Announce lock release
          */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
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
