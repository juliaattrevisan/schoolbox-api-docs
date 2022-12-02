<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Project\ResultCache;

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

Project Result Cache Reloader

This populates the records for project_result, which is where project marks
are stored after they're derived from their constituent assessments.

Every project that exists in project_assessment will get re-derived and
stored into this caching table for rapid retrievals later.

Usage: 02-project-cache-reload.php


HELP;
            exit;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::MY_TOOL_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            exit;
        }

        $this->getLogger()->info('Seeding project result cache');

        /* Find all the project ids that we must consider
         *
         * If there are no projects to manage, just exit early
         */
        $assessmentCountStatement = $this->getDatabaseInternal()->executeQuery("
            SELECT COUNT(*) AS num
            FROM project
            WHERE deleted_at IS NULL
        ");

        $assessmentCount = (int)$assessmentCountStatement->fetch(\PDO::FETCH_ASSOC)['num'];

        /* This instance has no living projects to deal with.
         * Let's get out of here
         */
        if (!$assessmentCount) {
            return;
        }

        /* Otherwise let's dig
         */
        $assessmentStatement = $this->getDatabaseInternal()->executeQuery("
            SELECT assessment_id
            FROM project
            WHERE deleted_at IS NULL
        ");

        /* Find all the users who *might* have participated
         * in all these projects. If they're not participants
         * then the persist() method will skip over them anyway.
         *
         * This saves us from digging out involved humans for
         * every project instance that existed, which is bleagghh
         *
         * As for deleted users...not sure how they would ever see
         * their marks, nor re-see them, once they've met such a fate
         * so I suppose we can just ignore those
         */
        $users = \Schoolbox\UserMapper::getInstance()->findAll();

        /* I need this assessment mapper cache because of all the
         * participation insanity that we have manufactured
         */
        /** @var AssessmentDataSource $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        /* ResultCache instance
         */
        $projectResultCacheMapper = ResultCache\Mapper::getInstance();
        $projectResultCache = new ResultCache\ResultCache($projectResultCacheMapper);

        /* TEMPORARY MARKER GUY
         * TODO: What are we gonna do with multiple teachers marking parts of a project
         */
        $marker = \Schoolbox\UserMapper::getInstance()->findById(1);

        try {
            while ($row = $assessmentStatement->fetch(\PDO::FETCH_ASSOC)) {
                $this->refreshLock($lock);

                $project = $assessmentMapperProxy->findByIdForReview($row['assessment_id']);

                /* TODO Maybe find $users as the set of guys inside $project somehow
                 */
                foreach ($users as $user) {
                    $outcome = $projectResultCache->persist($project, $user, $marker);

                    if (null === $outcome) {
                        $this->getLogger()->debug(sprintf(
                            '%s (%s) was not a participant in project %s (%s)',
                            $user->getFullname(),
                            $user->getId(),
                            $project->getTitle(),
                            $project->getId()
                        ));
                    } else {
                        $this->getLogger()->info(sprintf(
                            'Persisting marks for %s (%s) in project %s (%s)',
                            $user->getFullname(),
                            $user->getId(),
                            $project->getTitle(),
                            $project->getId()
                        ));
                    }
                }
            }
        } catch (\Alaress\Lock\LockRefreshException $e) {
            /* Lock TTL expired; just leave politely
             */
            return;
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
