<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Lumberjack\Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Helper;

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
     * --input
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_INPUT = 'reference:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INPUT
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Learnosity Activity Cleanser

Archives all the Learnosity Activities we've created because there's no mass-removal
option in a ui somewhere. You can feed it a particular Activity Reference identifier
to cleanse it; otherwise it'll remove all of them.

Note that Learnosity cannot hard-delete anything, it always soft-deletes everything.
In their world, soft-delete is synonymous with "archiving"

It's not very fast. It's an internal tool to help us manage all the broken data we make.

Usage: cleanse.php [--reference]
Example: cleanse.php --reference=396b91d9-9fb7-4667-bd4d-6af465a4fc33

HELP;
            return;
        }

        /* Don't have access to $_SERVER superglobal here
         */
        $serverName = 'vagrant.dev.schoolbox.cloud';

        $exclusions = [
            '4d3f93b4-b13d-45b0-b949-db823f1b5ec2'
        ];

        /* Probably inject this one day, but for now,
         * I want to avoid dumping Yet Another Thing into the kernel
         * I don't have a convenient means to access SERVER_NAME except like this, sadly
         */
        $api = new Api(
            $serverName,
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger()
        );

        $helper = (new Helper($api, $this->getLogger()));

        /* Cleanses everything except references in $exclusions
         */
        $activities = $api->dataGetRequest('/itembank/activities', [
            'status' => [
                /* This is meant to be default behaviour, but it's broken in Learnosity
                 */
                'published', 'unpublished'
            ]
        ]);

        foreach ($activities['data'] as $activity) {
            $activityReference = $activity['reference'];

            $this->getLogger()->info(sprintf(
                'Processing %s',
                $activityReference
            ));

            /* Skip over exclusions (used when you have meticulously
             * hand-crafted a quiz for showcase purposes and you don't
             * want to lose it, for example)
             */
            if (in_array($activity['reference'], $exclusions, true)) {
                $this->getLogger()->info(sprintf(
                    'Skipped %s - is in exclusions list',
                    $activityReference
                ));

                continue;
            }

            /* Schoolbox devs only: mass soft-deleted data that we may have spawned
             * There's no convenient way except to slap in identifiers yourself
             */
            try {
                $helper->archive($activityReference);

                $this->getLogger()->info(sprintf(
                    'Archived %s',
                    $activityReference
                ));
            } catch (\RuntimeException $e) {
                $this->getLogger()->warning($e->getMessage());
            }
            
            $this->getLogger()->info('');
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
