<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

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
     * Temporary lock name
     */
    private const SCOMO_LOCK_NAME = 'ScottMorrison';


    /**
     * Temporary 10 sec ttl
     */
    private const LOCK_TTL = 2;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->>acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::SCOMO_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            exit();
        }

        for ($i = 0; $i < 1000; $i++) {
            $this->getLogger()->warning(sprintf(
                'Do something dangerous %s - hit ctrl-c to simulate an early exit',
                $i
            ));

            /* Give you a chance to ctrl-c
             */
            sleep(3);

            $lock->refresh();
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