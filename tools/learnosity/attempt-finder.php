<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Codec\UserIdentifier;
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
     * --server
     */
    private const OPT_LONG_SERVER = 'server:';


    /**
     * --schoolcode
     */
    private const OPT_LONG_SCHOOLCODE = 'schoolcode:';


    /**
     * --user
     */
    private const OPT_LONG_USER = 'user:';


    /**
     * --assessment
     */
    private const OPT_LONG_ASSESSMENT = 'assessment:';


    /**
     * --attempt
     */
    private const OPT_LONG_ATTEMPT_LEARNOSITY_REFERENCE = 'attempt:';


    /**
     * --recon
     *
     * Enable recon mode which investigates all learnosity references
     */
    private const OPT_LONG_RECON = 'recon';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_SERVER,
            self::OPT_LONG_SCHOOLCODE,
            self::OPT_LONG_USER,
            self::OPT_LONG_ASSESSMENT,
            self::OPT_LONG_ATTEMPT_LEARNOSITY_REFERENCE,
            self::OPT_LONG_RECON
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

* user_id and school code as a concatenated string
* assessment id

...in an effort to track down whether an attempt actually exists or not.

(Note that for the url https://vagrant.dev.schoolbox.cloud/learning/quiz/1576/attempt/13/89fb1a57....
the assessment id is 1576.)
 

Usage: attempt-finder.php --server=... --schoolcode=... --user=... --assessment=... [--attempt=...]
        --server         Server name; default is vagrant.dev.schoolbox.cloud
        --schoolcode     School code of the instance that spawned the quiz
        --user           Schoolbox user id who did the attempt
        --assessment     Schoolbox assessment id as per the url for an attempt
        --attempt        If specified, look for this particular learnosity reference



Alternative usage: Recon Mode

Sometimes you just want to see how many quiz attempts have a broken learnosity reference because you
don't have any first-hand information/reports from schools about which ones are broken. This mode
interrogates every learnosity reference known to schoolbox to see if there's a corresponding record(s)
in learnosity itself; anything broken will be announced.

Alt usage: attempt-finder.php --recon

HELP;
            return;
        }

        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Attempt Finder.');
        $this->getLogger()->info('');

        /* Don't have access to $_SERVER superglobal here: you need to specify it manually
         * Learnosity has some strict requirements about where api requests originate
         */
        $serverName = trim((string)$this->getOpt(self::OPT_LONG_SERVER));

        /* Find all the sessions in learnosity pertaining to a user id
         * You need to know that correctly and in advance as a cli arg
         */
        $schoolCode = trim((string)$this->getOpt(self::OPT_LONG_SCHOOLCODE));
        $userId = trim((string)$this->getOpt(self::OPT_LONG_USER));
        $assessmentId = trim((string)$this->getOpt(self::OPT_LONG_ASSESSMENT));

        if ($this->getOpt(self::OPT_LONG_RECON)) {
            /* Look at all learnosity references chunks of 1000
             */
        } else {
            /* Look for a particular record, or subset of records, for a particular person
             */
            $this->surgical($serverName, $schoolCode, $userId, $assessmentId);
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


    /**
     * Using the cli args, find the learnosity sessions that correspond. This is used when you know
     * the details of what you're interrogating, such as user/assessment/attempt
     *
     * @param string $serverName
     * @param string $schoolCode
     * @param string $userId
     * @param string $assessmentId
     * @return void
     */
    private function surgical(
        string $serverName,
        string $schoolCode,
        string $userId,
        string $assessmentId
    ): void {
        /* All four are required
         */
        if ('' === $serverName) {
            $this->getLogger()->info('Please provide the server name for this instance.');
            return;
        }

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

        /* This is an optional constraint
         */
        $attemptReference = trim((string)$this->getOpt(self::OPT_LONG_ATTEMPT_LEARNOSITY_REFERENCE));

        /* Some identifying info
         */
        try {
            $user = UserMapper::getInstance()->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->getLogger()->warning(sprintf(
                'schoolbox user %s not found.',
                $userId
            ));

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

        $userIdentifier = (new UserIdentifier())->generate($schoolCode, $userId);

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

        /* Optional constraint
         */
        if ('' !== $attemptReference) {
            $payload['session_id'] = [
                $attemptReference
            ];
        }

        /* Perform non-mutating get request
         */
        $result = $api->dataGetRequest('/sessions/responses', $payload);

        /* Iterate through each of the records found and show some abbreviated details for attempts
         * that match the provided cli args, including the optional attempt learnosity reference if
         * it was provided and meaningful. This is pretty low-level details, exactly as is, for the
         * most part. You might need a dev to help decipher what might have happened.
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
    }


    /**
     * Scan all learnosity references
     *
     * @return void
     */
    private function recon(): void
    {

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
