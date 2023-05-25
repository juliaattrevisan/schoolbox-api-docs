#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Course\Task\Completion;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Auto marks tasks from a specified course as complete if the student has 'completed' the task
 * Build specifically for help.schoolbox.com.au
 */
$app = new class extends CliTool\App {
// --help: print help
    private const OPT_LONG_HELP = 'help';
    private const OPT_COURSE_ID = 'course:';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_COURSE_ID,
        ];
    }

    /** @inheritdoc */
    public function runAsRoot(): ?bool
    {
        return false;
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if (strtolower($this->getConfig()->get('site_school_code')) !== 'help') {
            die("Not HELP stopping...\n");
        }

        if ($this->getOpt(self::OPT_LONG_HELP) || !(bool)$this->getOpt(self::OPT_COURSE_ID)) {
            echo <<<HELP
Finds all tasks from within the specified course Id.
Auto completes all student completed tasks where a teacher has not marked as complete

Usage: autoCompleteTasks.php --course 34 [-v] [-p]
        --course required course to automark tasks
        -p Actually mark as complete, else just display what would occur
        -v Verbose mode (may be provided up to 3 times for increasing verbosity)

HELP;
            exit;
        }

        if (!$this->perform()) {
            $this->getLogger()->alert("Preview Mode");
        } else {
            $this->getLogger()->info("Action Mode");
        }

        $db     = $this->getDatabaseInternal();

        // running log of updated entries
        $updated = [];

        // finds all tasks that require to update
        $courseTaskStatement = $db->prepareQuery("select `t`.id as id from task as `t`
            join assessment_relation on assessment_relation.assessment_id = `t`.assessment_id
            join course_implementation on (assessment_relation.course_implementation_id = course_implementation.id and course_implementation.course_specification_id = :courseId);
        ");
        $courseTaskStatement->execute(['courseId' => $this->getOpt(self::OPT_COURSE_ID)]);
        $tasks = $courseTaskStatement->fetchColumnArray('id');

        // find all completion status for the above tasks
        $taskCompletionMapper = \Schoolbox\Assessment\Course\Task\CompletionMapper::getInstance();
        $completionEntries = $taskCompletionMapper->findByColumn('task_id', $tasks)->sortBy('id', 'desc');

        // loop through the tasks / students and find only those with no completion_status == 3 (teacher complete)
        /** @var Schoolbox\Assessment\Course\Task\Completion $completionEntry */
        foreach ($completionEntries as $completionEntry) {
            $this->getLogger()->debug(sprintf("Inspecting Assessment: %s, studentId: %s", $completionEntry->getTask()->getAssessmentModel()->getId(), $completionEntry->getStudent()->getId()));

            // remember what we are looking at, so we don't look at it again...
            $token = sprintf("%s_%s", $completionEntry->getTask()->getAssessmentModel()->getId(), $completionEntry->getStudent()->getId());
            //have we seen this already?
            if (in_array($token, $updated)) {
                //skip and move along
                $this->getLogger()->debug(sprintf("Token: %s already been processed, skipping...", $token));
                continue;
            }
            $updated[] = $token;

            // find if this entry has a teacher complete status
            //all the tmp variables
            $thisTaskList = $completionEntries->filterBy('task', $completionEntry->getTask());
            $thisStudentAssessment = $thisTaskList->getBy('student', $completionEntry->getStudent());
            if (Completion::STATUS_TEACHER_ASSESSED_COMPLETE === $thisStudentAssessment->getcompletionStatus()) {
                $this->getLogger()->debug("Task Complete");
                continue;
            }
            if (Completion::STATUS_INCOMPLETE === $thisStudentAssessment->getcompletionStatus()) {
                $this->getLogger()->debug("Task Not Complete");
                continue;
            }

            //this assessment has no teacher feedback, so lets add that in now, making sure not to do this multiple times
            if ($this->perform()) {
                $this->getLogger()->info(sprintf("Inserting Task Complete Entry for TaskId: %s, studentId: %s", $completionEntry->getTask()->getId(), $completionEntry->getStudent()->getId()));
                $newStatusStatment = $db->prepareQuery("
                    INSERT INTO
                    task_completion
                        (`task_id`, `student_id`, `marking_user_id`, `completion_date`, `completion_status`)
                    VALUES
                        (:task_id, :student_id, :marking_user_id, NOW(), :completion_status)
                ");
                $newStatusStatment->execute(['task_id' => $completionEntry->getTask()->getId(), 'student_id' => $completionEntry->getStudent()->getId(), 'marking_user_id' => 1, 'completion_status' => Completion::STATUS_TEACHER_ASSESSED_COMPLETE]);
            } else {
                $this->getLogger()->info(sprintf("Assessment: %s, studentId: %s Not Complete, would have updated", $completionEntry->getTask()->getAssessmentModel()->getId(), $completionEntry->getStudent()->getId()));
            }
        }
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
