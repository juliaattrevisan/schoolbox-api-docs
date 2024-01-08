<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan\Cli;

use Schoolbox\Application\CliTool;

class DupeDetectorTool extends CliTool\App
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

Lesson Plan Dupe Detector

This detects when a lesson_plan.assessment_id belongs to multiple lesson_plan.id
and gives you a little summary about them.

This wasn't intended and seems to be some kind of corrupt data. This doesn't mutate
any data; it just tells you about dupes. You must decide what happens next and 
effect any changes manually yourself.

(Typically we anticipate you needing to decide which rows to delete from the 
schoolbox.lesson_plan table, removing dupes)

Usage: lesson-plan-dupe-detector.php


HELP;
            return;
        }

        /* Set up a custom query to find dupes in lesson_plan
         * where the same assessment id belongs to multiple lesson plans
         */
        $statement = $this->getDatabaseInternal()->executeQuery("
            SELECT
                assessment.id AS assessment_id, 
                assessment.title, 
                assessment.deleted_at AS assessment_deleted_at,
                lesson_plan.id AS lesson_plan_id,
                lesson_plan.deleted_at AS lesson_plan_deleted_at
            FROM lesson_plan
            INNER JOIN assessment ON lesson_plan.assessment_id = assessment.id
            WHERE assessment_id IN (
                SELECT assessment_id
                FROM lesson_plan
                GROUP BY assessment_id
                HAVING COUNT(assessment_id) > 1
            )
        ");

        /* Find and disclose some metadata about assessments that seem brokenly assigned
         */
        $lastSeen = null;

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if ($lastSeen !== (int)$row['assessment_id']) {
                $this->getLogger()->info('');

                $this->getLogger()->info(sprintf(
                    '"%s" (assessment_id %s) is assigned to these lesson plans: ',
                    $row['title'],
                    $row['assessment_id']
                ));

                if ($row['assessment_deleted_at']) {
                    $this->getLogger()->info(sprintf(
                        'This assessment was deleted on %s',
                        $row['assessment_deleted_at']
                    ));
                }
            }

            /* Show the lesson plan metadata for this assessment
             */
            if ($row['lesson_plan_deleted_at']) {
                $this->getLogger()->info(sprintf(
                    '    %s (deleted at: %s)',
                    $row['lesson_plan_id'],
                    $row['lesson_plan_deleted_at']
                ));
            } else {
                $this->getLogger()->info(sprintf(
                    '    %s',
                    $row['lesson_plan_id'],
                ));
            }

            $lastSeen = (int)$row['assessment_id'];
        }

        $this->getLogger()->info('');
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