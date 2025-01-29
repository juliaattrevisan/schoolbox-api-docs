<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api as LearnosityApi;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Url\Factory;

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
     * Arbitrarily assign a status (either "archived" or "published")
     * to the quizzes.
     *
     * Used to change state via the cli without needing to edit this tool
     */
    private const OPT_LONG_NEW_STATUS = 'status:';


    /**
     * A placeholder for the status of a quiz prior to finding out from Learnosity
     */
    private const STATUS_UNKNOWN = 'unknown';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_REFERENCE,
            self::OPT_LONG_NEW_STATUS
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
Specify one(s) to restore with the cli arg --reference

If you use --perform without a --reference, this tool updates the status of everything it found.
 
You can arbitrarily decide which state to apply to a Learnosity quiz: :archived: or :published"
by using the --status arg. This allows you to assign "published" if you omit it. To reverse this,
you can use --status=archived.

// Restores abcd123 to an undeleted (published) state in Learnosity
restore.php --reference=abcd123 --status=published --perform -vvv

// Put abcd123 into a deleted (archived) state in Learnosity
restore.php --reference=abcd123 --status=archived --perform -vvv
 

Usage: restore.php [--reference] [--status] [--perform]
        --reference      A Learnosity activity reference to restore
        --status         Arbitrarily assign "archived" or "published" (if ommitted, assumes "published")


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
        }
        catch (\RuntimeException $e) {
            return;
        }

        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Activity Restore');
        $this->getLogger()->info('');

        /* Choose which state to assign to the quizzes
         */
        $newStatus = trim((string)$this->getOpt(self::OPT_LONG_NEW_STATUS));

        /* If ommited, assume "published"
         */
        if ('' === $newStatus) {
            $newStatus = Helper::LEARNOSITY_ITEM_STATUS_PUBLISHED;
        }

        /* Otherwise check that it's one of the allowed state; if not, get out of here
         */
        if (!in_array($newStatus, [Helper::LEARNOSITY_ITEM_STATUS_PUBLISHED, Helper::LEARNOSITY_ITEM_STATUS_ARCHIVED])) {
            $this->getLogger()->warning(sprintf(
                'Unknown status: %s. Exiting',
                $newStatus
            ));

            return;
        }

        try {
            /* Fetch a list of quizzes that haven't concluded yet
             * (finish is in the future, regardless of whenever it started)
             *
             * Note that it's possible for learnosity_reference to be null or empty,
             * or otherwise bad: exclude those from the get-go
             *
             * TODO maybe parameterise this date range
             */
            $query = "
                SELECT
                    a.assessment_id,
                    a.title,
                    a.finish,
                    a.learnosity_reference,
                    mvar.folder_id,
                    f.name AS folder_name,
                    mvar.course_implementation_id,
                    mvar.course_unit_implementation_id,
                    mvar.course_specification_id,
                    mvar.course_unit_specification_id
                FROM assess a
                INNER JOIN mv_assessment_relation mvar ON a.assessment_id = mvar.assessment_id
                INNER JOIN folder f ON mvar.folder_id = f.id
                WHERE a.learnosity_reference IS NOT NULL
                AND a.learnosity_reference != ''
                AND (a.finish >= NOW() OR a.finish IS NULL)
                AND a.deleted_at IS NULL
            ";

            /* Filter to a specific reference, if provided
             */
            $specificReference = trim((string)$this->getOpt(self::OPT_LONG_REFERENCE));

            if ('' !== $specificReference) {
                $query .= " AND a.learnosity_reference = '{$specificReference}'";
            }

            $statement = $this->getDatabaseInternal()->prepare($query);
            $statement->execute();

            /* Store some info about them
             */
            $candidates = [];

            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $this->refreshLock($lock);

                /* A spec or an impl?
                 */
                if ((null !== $row['course_implementation_id']) || (null !== $row['course_unit_implementation_id'])) {
                    $type = 'implementation';
                } elseif ((null !== $row['course_specification_id']) || (null !== $row['course_unit_specification_id'])) {
                    $type = 'specification';
                } else {
                    $type = 'implementation (not in a course)';
                }

                $candidates[$row['learnosity_reference']] = [
                    /* Spittin' facts
                     */
                    'assessment_id' => $row['assessment_id'],
                    'title' => $row['title'],
                    'finish' => $row['finish'],
                    'learnosity_reference' => $row['learnosity_reference'],
                    /* A spec or an impl?
                     */
                    'type' => $type,
                    /* Some info about where you might find it in the browzer
                     */
                    'folder_id' => $row['folder_id'],
                    'folder_name' => $row['folder_name'],
                    /* These get updated as we discover information/facts from Learnosity itself
                     */
                    'status' => self::STATUS_UNKNOWN
                ];
            }

            /* No candidates? No trabajo
             */
            if (!\count($candidates)) {
                $this->getLogger()->info('No candidate quizzes found; nothing to do. Exiting');

                return;
            }

            /* Find the learnosity details about each one. Learnosity only allows 1000
             * so you'll have to chunk them before compiling them...if you have that many
             *
             * In demo data, the learnosity_reference may actually be null which we should skip;
             * I'm not sure why. Obviously we can't ask Learnosity about more info about those ones
             */
            $activityReferences = array_values(array_filter(array_column($candidates, 'learnosity_reference')));

            /* Build dependencies
             */
            $api = new LearnosityApi(
                (new Factory($this->getConfig()))->buildFullyQualified()->getHost(),
                $this->getConfig()->get('learnosity_version'),
                $this->getConfig()->get('learnosity_consumer_key'),
                $this->getConfig()->get('learnosity_consumer_secret'),
                $this->getLogger(),
            );

            /* For access to the restore() mechanism
             */
            $helper = (new Helper($api, $this->getLogger()));

            /* Ask them about the learnosity references we obtained. If we die for any reason,
             * probably have to exit out: we can't really continue from here
             */
            $this->refreshLock($lock);

            try {
                $result = $api->dataGetRequest('/itembank/activities', ['references' => $activityReferences]);
            } catch (JsonException | ValidationException $e) {
                $this->getLogger()->error('Request to /itembank/activities failed.  ' . $e->getMessage());

                return;
            }

            /* Collate some facts from learnosity's perspective
             *
             * Note that learnosity might give you a malformed data set for some reason or other
             * Store them in a hash lookup for quick cross-referencing anyway
             */
            foreach (($result['data'] ?? []) as $learnosityQuiz) {
                $candidates[$learnosityQuiz['reference']]['status'] = $learnosityQuiz['status'];
            }

            /* Planning mode; just show the facts, then leave
             */
            $n = \count($candidates);
            $i = 0;

            foreach ($candidates as $learnosityReference => $candidate) {
                $this->refreshLock($lock);

                $i++;

                /* Yeah, formatting etc
                 * Logs never look nice for humans
                 */
                $this->getLogger()->info('');
                $this->getLogger()->info('');

                $this->getLogger()->info(sprintf(
                    "[%s/%s] %s",
                    $i,
                    $n,
                    $candidate['title'],
                ));

                $this->getLogger()->info(sprintf(
                    'Assessment: /learning/assessments/%s',
                    $candidate['assessment_id'],
                ));

                $this->getLogger()->info(sprintf(
                    'Context: "%s" /homepage/%s',
                    $candidate['folder_name'],
                    $candidate['folder_id']
                ));

                $this->getLogger()->info(sprintf(
                    'Type: %s',
                    $candidate['type']
                ));

                $this->getLogger()->info(sprintf(
                    'Reference: %s',
                    $candidate['learnosity_reference'],
                ));

                $this->getLogger()->info(sprintf(
                    'Status: %s',
                    $candidate['status'],
                ));

                /* In Perform mode, perform the restoration of either the one(s) specified
                 * with --reference, or alternatively, all the ones we just found.
                 */
                if ($this->perform()) {
                    $this->refreshLock($lock);

                    /* If a quiz is already published, nothing to change
                     */
                    if ($newStatus === $candidate['status']) {
                        $this->getLogger()->info(sprintf(
                            '    This quiz status is already "%s"; nothing to change. Skipping',
                            $newStatus
                        ));

                        continue;
                    }

                    /* We can't...really restore quizzes learnosity doesn't understand.
                     * I'm not sure why it exists: might only be in vagrant demo data
                     */
                    if (self::STATUS_UNKNOWN === $candidate['status']) {
                        $this->getLogger()->info('    Learnosity cannot figure out this quiz. Skipping');

                        continue;
                    }

                    /* Otherwise a learnosity reference might have an alphanumeric value, but still
                     * be invalid - someone might have just made that whole thing up, so skip over
                     * those if we encounter them. Maybe it's bad data; either way, can't do much
                     */

                    /* Perform "unarchive"
                     */
                    $this->getLogger()->info(sprintf(
                        '    Requesting change to new status "%s"...',
                        $newStatus
                    ));

                    if (Helper::LEARNOSITY_ITEM_STATUS_PUBLISHED === $newStatus) {
                        $helper->restore($candidate['learnosity_reference']);
                    } elseif (Helper::LEARNOSITY_ITEM_STATUS_ARCHIVED === $newStatus) {
                        $helper->archive($candidate['learnosity_reference']);
                    }

                    /* Log status of the ones we just changed, requesting their current state
                     * from learnosity as a confirmation
                     */
                    try {
                        $confirmation = $api->dataGetRequest('/itembank/activities', [
                            'references' => [
                                $candidate['learnosity_reference']
                            ]
                        ]);
                    } catch (JsonException | ValidationException $e) {
                        $this->getLogger()->error('Request to /itembank/activities failed.  ' . $e->getMessage());

                        return;
                    }

                    $this->getLogger()->info(sprintf(
                        '    Updated status: %s',
                        $confirmation['data'][0]['status'] ?? 'unknown'
                    ));
                }
            }

            $this->refreshLock($lock);
        } catch (\Alaress\Lock\LockRefreshException $e) {
            /* Lock TTL expired; just leave politely
             */
            return;
        }

        /* Announce conclusion
         */
        $this->getLogger()->info('');

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
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
