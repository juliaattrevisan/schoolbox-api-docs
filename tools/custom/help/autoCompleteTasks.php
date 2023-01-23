#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

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
            self::OPT_COURSE_ID
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
            $this->getLogger()->alert("Action Mode");
        }

        $db     = $this->getDatabaseInternal();

        // finds all tasks that require to update
        $statement = $db->prepareQuery("select `t`.`assessment_id`    AS `assessment_id`,
                       'task_completion'      AS `feedback_table`,
                       max(`tc`.`id`)         AS `feedback_id`,
                       `tc`.`student_id`      AS `student_id`,
                       `tc`.marking_user_id AS `reviewer`,
                       `tc`.`completion_date` AS `published_at`,
                       `tc`.`completion_status` AS `completion_status`

                from ((`schoolbox`.`task_completion` `tc`
                    join (select
                              `schoolbox`.`task_completion`.`task_id` AS `task_id`,
                              `schoolbox`.`task_completion`.`student_id`           AS `student_id`,
                              max(`schoolbox`.`task_completion`.`completion_date`) AS `max_completion_date`,
                              max(`schoolbox`.`task_completion`.`completion_status`) AS `completion_status`
                          from `schoolbox`.`task_completion`
                          where `schoolbox`.`task_completion`.`deleted_at` is null
                          group by `schoolbox`.`task_completion`.`task_id`,
                                   `schoolbox`.`task_completion`.`student_id`) `t1`
                       on (`tc`.`task_id` = `t1`.`task_id` and `tc`.`student_id` = `t1`.`student_id` and
                           `tc`.`completion_date` = `t1`.`max_completion_date` and 2 = `t1`.completion_status ))
                    join `schoolbox`.`task` `t` on (`t`.`id` = `tc`.`task_id`)
                    join v_assessment_relation on v_assessment_relation.assessment_id = `t`.assessment_id
                    join course_implementation on (v_assessment_relation.course_implementation_id = course_implementation.id and course_implementation.course_specification_id = :courseId))
                group by `t`.`assessment_id`, `tc`.`student_id`");
        $statement->execute(['courseId' => $this->getOpt(self::OPT_COURSE_ID)]);
        while ($taskFeedback = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $this->getLogger()->info(
                sprintf(
                    "Assessment: %s, Student: %s, feedbackId: %s",
                    $taskFeedback['assessment_id'],
                    $taskFeedback['student_id'],
                    $taskFeedback['feedback_id']
                )
            );
        }
        $statement->close();

        $this->getLogger()->info("All Complete.");
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
