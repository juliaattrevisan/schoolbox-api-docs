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


Primary usage: Surgical Mode
----------------------------
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
-----------------------------
Sometimes you just want to see how many quiz attempts have a broken learnosity reference because you
don't have any first-hand information/reports from schools about which ones are broken. This mode
interrogates every learnosity reference known to schoolbox to see if there's a corresponding record(s)
in learnosity itself; anything broken will be announced.

Alt usage: attempt-finder.php --server=... --recon
        --server         Server name; default is vagrant.dev.schoolbox.cloud
        
        
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

        if ('' === $serverName) {
            $this->getLogger()->info('Please provide the server name for this instance.');
            return;
        }

        /* Find all the sessions in learnosity pertaining to a user id
         * You need to know that correctly and in advance as a cli arg
         */
        $schoolCode = trim((string)$this->getOpt(self::OPT_LONG_SCHOOLCODE));
        $userId = trim((string)$this->getOpt(self::OPT_LONG_USER));
        $assessmentId = trim((string)$this->getOpt(self::OPT_LONG_ASSESSMENT));

        /* Initialise dependencies
         */
        $api = new Api(
            $serverName,
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger()
        );

        if ($this->getOpt(self::OPT_LONG_RECON)) {
            /* Look at all learnosity references chunks of 1000
             */
            $this->recon($api);
        } else {
            /* Look for a particular record, or subset of records, for a particular person
             */
            $this->surgical($api, $schoolCode, $userId, $assessmentId);
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
     * @param Api $api
     * @param string $schoolCode
     * @param string $userId
     * @param string $assessmentId
     * @return void
     */
    private function surgical(
        Api $api,
        string $schoolCode,
        string $userId,
        string $assessmentId
    ): void {
        /* All four are required incl. server name
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
     * Bust down into 1000-element chunks to minimise learnosity api requests or you can specify
     * your own chunk size.
     *
     * @param Api $api
     * @param int $maxPerChunk
     * @return void
     */
    private function recon(Api $api, int $maxPerChunk = 1000): void
    {
        $db = $this->getDatabaseInternal();

        $attempts = $db->executeQuery("
            SELECT 
                aa.learnosity_reference, aa.user_id,
                u.firstname, u.lastname 
            FROM assess_attempt aa
            INNER JOIN `user` u ON u.id = aa.user_id 
            WHERE aa.deleted_at IS NULL
            AND aa.learnosity_reference IS NOT NULL
            AND aa.learnosity_reference != ''
            ORDER BY aa.start
        ");

        $rows = $attempts->fetchAll();

        $chunks = array_chunk($rows, $maxPerChunk);

        $stack = [];

        $i = 0;

        foreach ($chunks as $chunk) {
            $this->getLogger()->debug(sprintf(
                'Inspecting chunk %s of size %s...',
                ++$i, $maxPerChunk
            ));

            $substack = [];

            foreach ($chunk as $row) {
                $substack[$row['learnosity_reference']] = $row;
            }

            /* Compile learnosity references for a batch request to learnosity
             */
            $learnosityReferences = array_keys($substack);

            $payload = [
                'session_id' => $learnosityReferences,
            ];

            $result = $api->dataGetRequest('/sessions/responses', $payload);

            foreach (($result['data'] ?? []) as $record) {
                /* Unset substack if we found a learnosity reference
                 */
                unset($substack[$record['session_id']]);
            }

            /* Merge anything that remains
             */
            if (!empty($substack)) {
                $stack = array_merge($stack, $substack);
            }
        }

        /* Format the output nicely somewhat
         */
        if (\count($stack)) {
            foreach ($stack as $learnosityReference => $brokenRecord) {
                $this->getLogger()->info(sprintf(
                    'Attempt for learnosity reference %s by %s %s (%s) is not in learnosity.',
                    $learnosityReference,
                    $brokenRecord['firstname'],
                    $brokenRecord['lastname'],
                    $brokenRecord['user_id']
                ));
            }
        } else {
            $this->getLogger()->info('All references located in learnosity; nothing to do.');
        }

        $this->getLogger()->info('');
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
