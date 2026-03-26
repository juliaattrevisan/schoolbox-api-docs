<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

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

Replicate settings for rubric grids

This clones out settings for each submission_box's rubric settings into rubric_instance, mapping submission_box.id to
its corresponding assessment_id. Capability settings are simply copied into rubric_instance accordingly. use_rubric is
used to decide which submission boxes get their settings cloned: anything with a falsey use_rubric value will be ignored
under the premise that you didn't want rubrics there (even if it had rubric settings) because you turned it off

This tool is intended to be a one-use-only as part of an upgrade path and always runs in perform mode.

Usage: 02-replicate-settings.php [-vvv]


HELP;
            return;
        }


        $this->getLogger()->info('Cloning submission_box rubric settings into rubric_instance...');

        /* Find all the submission_boxes that:
         *    - have a truthy use_rubric value
         *    - are still alive (deleted_at is null)
         *    - are connected to a still-alive assessment (in the odd circumstance that we had bad data)
         */
        $statement = $this->getDatabaseInternal()->prepare("
            SELECT 
                sb.id, 
                sb.assessment_id,
                sb.rubric_capability_mark_type_id,
                sb.rubric_capability_grade_group_id,
                sb.rubric_mark_range
            FROM submission_box sb
            INNER JOIN assessment a on sb.assessment_id = a.id
            WHERE a.deleted_at IS NULL
        ");

        $statement->execute();

        /* How many rows were eventually found in the result set
         */
        $numFound = 0;

        /* Prepare a statement that we'll reuse throughout this loop
         */
        $insertStatement = $this->getDatabaseInternal()->prepare("
            INSERT INTO rubric_instance (
                assessment_id,
                rubric_capability_mark_type_id,
                rubric_capability_grade_group_id,
                rubric_mark_range,
                created_at,
                updated_at
            ) VALUES (
                :assessmentId,
                :rubricCapabilityMarkTypeId,
                :rubricCapabilityGradeGroupId,
                :rubricMarkRange,
                :createdAt,
                :updatedAt
            )
        ");

        /* A timestamp for when this conceptually occurred
         */
        $now = \Alaress_Date_Format::now()->getDatabase();

        $start = microtime(true);

        /* For each record in that result set, insert a new row into rubric_instance so that we:
         *    - save the rubric capability mark type setting
         *    - save the rubric capability grade group setting
         *    - save the mark range boolean setting
         *    - save the corresponding assessment_id so we know which assessment has this grid
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $numFound++;

            $insertStatement->execute([
                'assessmentId' => $row['assessment_id'],
                'rubricCapabilityMarkTypeId' => $row['rubric_capability_mark_type_id'],
                'rubricCapabilityGradeGroupId' => $row['rubric_capability_grade_group_id'],
                'rubricMarkRange' => $row['rubric_mark_range'],
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);

            $this->getLogger()->debug(sprintf(
                'Submission box %s (assessment %s) had these rubric settings: mark type %s, grade group %s, use mark range %s',
                $row['id'],
                $row['assessment_id'],
                $row['rubric_capability_mark_type_id'] ?: 'none',
                $row['rubric_capability_grade_group_id'] ?: 'none',
                $row['rubric_mark_range']
            ));
        }

        /* Add a timer
         */
        $end = microtime(true);

        /* Compare how many we expected to find vs how many we actually manufactured as an assertion we did it all
         */
        $numInserted = $this->getDatabaseInternal()->executeQuery("
            SELECT COUNT(*) AS num
            FROM rubric_instance
            WHERE deleted_at IS NULL
            AND created_at = :createdAt
        ", ['createdAt' => $now])->fetch()['num'];

        $this->getLogger()->info(sprintf(
            'Replication of %s submission boxes into %s rubric_instance rows took %s seconds.',
            $numFound,
            $numInserted,
            number_format($end - $start, 2)
        ));
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
        return false;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
