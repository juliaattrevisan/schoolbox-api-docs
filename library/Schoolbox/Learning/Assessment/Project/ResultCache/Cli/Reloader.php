<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Project\ResultCache\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Project\ResultCache;

/**
 * Preloads project_result with everyone's derived mark
 */
class Reloader extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const MY_TOOL_LOCK_NAME = 'project_cache_reload';


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
     * --first-id=FIRST_ID
     *
     * If this is provided: only attempt to reload project results for project
     * IDs which are equal to or greater than this ID.
     */
    private const OPT_LONG_FIRST_ID = 'first-id:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FIRST_ID,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Project Result Cache Reloader

This populates the records for project_result, which is where project marks
are stored after they're derived from their constituent assessments.

If the --first-id option is provided: only projects with an ID equal to or
greater than this value will be derived: otherwise, every project that exists in
project_assessment will get re-derived and stored into this caching table for
rapid retrievals later.

(You may use the --first-id option to resume population of project_result, if
this tool failed partway through.)

User ids and assessment ids are shown in brackets.

Planning mode gives you a preview of what marks were found by the tool
and won't be persisted until --perform is specified.

* This tool updates records already existing in project_result
* This tool won't delete records from project_result
* Non-participant users are ignored though you shouldn't encounter this

Usage: projectResultCacheReload.php [--perform] [--first-id=FIRST_ID]


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

        $this->getLogger()->info('Seeding project result cache');

        // First ID to process, if it was provided: otherwise, default to 1
        $firstId = $this->getOptAsInt(self::OPT_LONG_FIRST_ID, 1);

        /* Find all the project ids that we must consider
         * (Only get Implementations, never Specifications)
         */
        $assessmentStatement = $this->getDatabaseInternal()->executeQuery("
            SELECT a.id AS assessment_id
            FROM   assessment a
            INNER JOIN project p ON a.id = p.assessment_id
            LEFT  JOIN assessment_course_unit_specification acus ON a.id = acus.assessment_id
            WHERE p.deleted_at IS NULL
            AND   a.deleted_at IS NULL
            AND   acus.course_unit_specification_id IS NULL
            AND a.id >= :firstId
            ORDER BY a.id
        ", ['firstId' => $firstId]);

        /* This instance has no living projects to deal with.
         * Let's get out of here
         */
        if ($assessmentStatement->rowCount() === 0) {
            $this->getLogger()->info('No project implementations found; exiting');
            return;
        }

        /* I need this assessment mapper cache because of all the
         * participation insanity that we have manufactured
         */
        /** @var AssessmentDataSource $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        /* ResultCache instance
         * Decide the persistence mode
         */
        $projectResultCacheMapper = ResultCache\Mapper::getInstance();
        $projectResultCache = (new ResultCache\ResultCache($projectResultCacheMapper))->setPerform($this->perform());

        try {
            foreach ($assessmentStatement->fetchAll() as $row) {
                $this->refreshLock($lock);

                $project = $assessmentMapperProxy->findByIdForReview($row['assessment_id']);
                if (!$project->isImplementation()) {
                    $this->getLogger()->info(
                        sprintf(
                            'Project %s (%s) is not an implementation, and thus has no participants - skipping',
                            $project->getTitle(),
                            $project->getId()
                        )
                    );
                    continue;
                }

                $this->getLogger()->info(sprintf(
                    'Examining project %s (%s)',
                    $project->getTitle(),
                    $project->getId()
                ));

                /* Find all the users who supposedly participated
                 * in all these projects.
                 *
                 * As for deleted users...not sure how they would ever see
                 * their marks, nor re-see them, once they've met such a fate
                 * so I suppose we can just ignore those
                 */
                $participants = $project->getAllParticipants();

                foreach ($participants as $participant) {
                    $this->refreshLock($lock);

                    $user = $participant->getUser();

                    /* Remember to ignore Excluded participants
                     *
                     * For some reason the proliferation of this is something
                     * you need to *just know* to do
                     */
                    if ($participant->isExcluded()) {
                        $this->getLogger()->debug(sprintf(
                            '%s (%s) was not a participant in project %s (%s) - they were excluded',
                            $user->getFullname(),
                            $user->getId(),
                            $project->getTitle(),
                            $project->getId()
                        ));

                        continue;
                    }

                    $outcome = $projectResultCache->persist($project, $user);

                    if (null === $outcome) {
                        /* This use case shouldn't emerge in this tool, but the
                         * project result cache knows how to reject non-participants
                         */
                        $this->getLogger()->debug(sprintf(
                            '%s (%s) was not a participant in project %s (%s)',
                            $user->getFullname(),
                            $user->getId(),
                            $project->getTitle(),
                            $project->getId()
                        ));
                    } elseif (null === $outcome->getHumanReadableMark()) {
                        /* The project had its Mark Type set to None, which returns a
                         * null upon aggregation of its parts. Project Result no longer
                         * accepts nulls
                         */
                        $this->getLogger()->info(sprintf(
                            'Null human-readable mark for %s (%s) in project "%s" (%s) - skipped. (The project Mark Type might have been set to None; its aggregator returned null.)',
                            $user->getFullname(),
                            $user->getId(),
                            $project->getTitle(),
                            $project->getId()
                        ));
                    } elseif ($outcome->getNormalisedMark() && $outcome->getHumanReadableMark()) {
                        /* Participants with a feedback(s) for this project
                         */
                        $this->getLogger()->info(sprintf(
                            'Persisting [%s, %s] for %s (%s) in project "%s" (%s)',
                            $outcome->getHumanReadableMark(),
                            $outcome->getNormalisedMark(),
                            $user->getFullname(),
                            $user->getId(),
                            $project->getTitle(),
                            $project->getId()
                        ));
                    } else {
                        /* Participants with no feedback(s) for this project
                         * Their normalised- and human-readable marks are null
                         */
                        $this->getLogger()->info(sprintf(
                            'No marks found for %s (%s) in project "%s" (%s)',
                            $user->getFullname(),
                            $user->getId(),
                            $project->getTitle(),
                            $project->getId()
                        ));
                    }
                }

                // Clean up?
                unset($participants, $project, $participant, $user, $outcome);
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
        return null;
    }
}
