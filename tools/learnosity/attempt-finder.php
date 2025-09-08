<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;

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
     * Learnosity has some strict requirements about where api requests originate
     */
    private const DEFAULT_SERVER_NAME = 'vagrant.dev.schoolbox.cloud';


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
    private const OPT_LONG_INPUT = 'input:';


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

Learnosity Attempt Finder

Whenever we encounter "Session ID Not Found" error it means we've lost the only bridge that connects
our assess_attempt record to a learnosity record. We can't query their db so we have to look at their
entire set of Learnosity Sessions (syn. "attempts") and try to find an attempt that matches at least:

* user_id and instance_id concatenated string

...and ideally, this:

* assessment title

...in an effort to track down whether an attempt actually exists or not. We didn't store assessment ids
which is unfortunate in retrospect, so we have to soft-match as best we can.

Usage: attempt-finder.php --server=... --identifier=... --title=...
        --server         Server name; default is vagrant.dev.schoolbox.cloud
        --identifier     A concatenation of instance_id and user_id
        --title          Assessment title as per the schoolbox assessment


HELP;
            return;
        }

        /* FIXME Main algorithm goes here
         */
        $this->getLogger()->info('Script commencing.');

        /* Don't have access to $_SERVER superglobal here
         * TODO Maybe make this into a cli arg if you use this outside of vagrant
         */
        $serverName = self::DEFAULT_SERVER_NAME;

        /* Initialise dependencies
         */
        $api = new Api(
            $serverName,
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger()
        );

        /* Find all the sessions in learnosity pertaining to a user id
         * You need to know that correctly and in advance as a cli arg
         */
        $instanceId = 'vagrant';
        $userId = 13;

        $userIdentifier = (new \Schoolbox\Learning\Learnosity\Codec\UserIdentifier())->generate($instanceId, $userId);

        $payload = [
            'user_id' => [
                $userIdentifier
            ]
        ];

        $result = $api->dataGetRequest('/sessions/responses', $payload);

        /* Iterate through each of the records found and hope to match the title provided
         * (if provided at all...) to find one that corresponds to a particular schoolbox assessment
         * because we didn't store assessment ids anywhere in the attempt payload.
         */
        $fields = array_fill_keys([
            'user_id',
            'activity_id',
            'num_attempted',
            'num_questions',
            'session_id',
            'score',
            'max_score',
            'dt_event',
            'dt_saved',
            'dt_started',
            'dt_completed',
            'responses',
            'metadata',
        ], null);

        foreach ($result['data'] as $row) {
            print_r(array_intersect_key($row, $fields));

        }

        /* Print any low-level info about the attempt if it happens to match the cli args enough
         */

        /* Announce conclusion
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
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
