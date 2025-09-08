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
     * --schoolcode
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_SCHOOLCODE = 'schoolcode:';


    /**
     * --user
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_USER = 'user:';


    /**
     * --assessment
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_ASSESSMENT = 'assessment:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_SCHOOLCODE,
            self::OPT_LONG_USER,
            self::OPT_LONG_ASSESSMENT,
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
* assessment id

Note that for the url https://vagrant.dev.schoolbox.cloud/learning/quiz/1576/attempt/13/89fb1a57....
the assessment id is 1576.

...in an effort to track down whether an attempt actually exists or not. 

Usage: attempt-finder.php --server=... --schoolcode=... --user=... --assessment=...
        --server         Server name; default is vagrant.dev.schoolbox.cloud
        --schoolcode     School code of the instance that spawned the quiz
        --user           Schoolbox user id who did the attempt
        --assessment     Schoolbox assessment id as per the url for an attempt


HELP;
            return;
        }

        $this->getLogger()->info('Learnosity Attempt Finder.');

        /* Don't have access to $_SERVER superglobal here
         * TODO Maybe make this into a cli arg if you use this outside of vagrant
         */
        $serverName = self::DEFAULT_SERVER_NAME;

        /* Find all the sessions in learnosity pertaining to a user id
         * You need to know that correctly and in advance as a cli arg
         */
        $schoolCode = trim((string)$this->getOpt(self::OPT_LONG_SCHOOLCODE));
        $userId = trim((string)$this->getOpt(self::OPT_LONG_USER));
        $assessmentId = trim((string)$this->getOpt(self::OPT_LONG_ASSESSMENT));

        /* All three are required
         */
        if ('' === $schoolCode) {
            $this->getLogger()->info('Please provide school code.');
            return;
        }

        if ('' === $userId) {
            $this->getLogger()->info('Please provide user id of the person who did the attempt.');
            return;
        }

        if ('' === $assessmentId) {
            $this->getLogger()->info('Please provide assessment id for the attempt.');
            return;
        }

        /* Initialise dependencies
         */
        $api = new Api(
            $serverName,
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger()
        );

        $user = \Schoolbox\UserMapper::getInstance()->findById($userId);
        $userIdentifier = (new \Schoolbox\Learning\Learnosity\Codec\UserIdentifier())->generate($schoolCode, $userId);

        /* Note: activity_id is where were stored our internal quiz identifier. Nice.
         * We had it after all! For example, the url below has 1576 as the activity_id:
         *
         * https://vagrant.dev.schoolbox.cloud/learning/quiz/1576/attempt/13/89fb1a57.........
         */
        $payload = [
            'activity_id' => [
                $assessmentId
            ],
            'user_id' => [
                $userIdentifier
            ]
        ];

        /* Perform non-mutating get request
         */
        $result = $api->dataGetRequest('/sessions/responses', $payload);

        /* Iterate through each of the records found and hope to match the title provided
         * (if provided at all...) to find one that corresponds to a particular schoolbox assessment
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

        /* Only show a subset of array nodes, for brevity
         */
        $i = 1;
        $n = \count($result['data']);

        /* For some reason this is in reverse chronological order
         */
        $results = array_reverse($result['data']);

        foreach ($results as $row) {
            $this->getLogger()->info(sprintf(
                '[%s/%s] attempt by %s saved at %s in learnosity',
                $i++,
                $n,
                $user->getFullName(),
                \Alaress_Date_Format::newFromTimestamp(strtotime($row['dt_saved']))->getDatabase()
            ));

            /* Print any low-level info about the attempt if it happens to match the cli args enough
             */
            print_r(array_intersect_key($row, $fields));
            echo "\n\n";
        }

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
