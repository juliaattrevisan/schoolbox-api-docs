#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Project\ResultCache as ProjectResultCache;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Auto marks all completed quiz
 * Build specifically for help.schoolbox.com.au
 */
$app = new class extends CliTool\App {
// --help: print help
    private const OPT_LONG_HELP = 'help';

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
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

        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

HELP;
            exit;
        }

        if (!$this->perform()) {
            $this->getLogger()->alert("Preview Mode");
        } else {
            $this->getLogger()->info("Action Mode");
        }

        $db     = $this->getDatabaseInternal();

        // get list of quiz attempts to complete
        $completedAttempts = $db->prepareQuery("SELECT
            aa.attempt_id as 'attemptId',
            aa.user_id as 'userId',
            a.assessment_id as 'assessmentId'
        FROM
            assess_attempt aa
                JOIN
            assess a ON aa.assess_id = a.assess_id
                AND a.availability = 'assess'
                JOIN
            user u ON aa.user_id = u.id
                AND u.deleted_at IS NULL
                JOIN
            folder c ON a.category_id = c.id
                AND c.deleted_at IS NULL
        WHERE
            aa.reviewed_at IS NULL
                AND aa.finish IS NOT NULL
                AND aa.deleted_at IS NULL
                AND aa.assess_id IN (SELECT
                    a.assess_id
                FROM
                    assess a
                        JOIN
                    assess_section ass ON a.assess_id = ass.assess_id
                        JOIN
                    assess_question aq ON ass.section_id = aq.section_id
                        AND aq.deleted_at IS NULL
                        JOIN
                    assess_luQuestionType lqt ON aq.type_id = lqt.type_id
                        AND lqt.automated_marking = TRUE
                GROUP BY assess_id)
        ORDER BY aa.finish DESC;");

        $completedAttempts->execute();

        /** @var AssessmentDataSource $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');
        $projectCache = new ProjectResultCache\ResultCache(ProjectResultCache\Mapper::getInstance());
        $userMapper = \Schoolbox\UserMapper::getInstance();

        // mark the quiz as complete // store the student / assessment
        while ($row = $completedAttempts->fetch()) {
            if ($this->perform()) {
                $this->getLogger()->info(sprintf("Updating quiz attempt: %s for user: %s, and project results updated", $row['assessmentId'], $row['userId']));
                $this->updateAttempt($row['attemptId']);
                // reload project cache for that assessment / student
                /** @var Schoolbox\Learning\Assessment\Quiz\Model $quiz **/
                $quiz = $assessmentMapperProxy->findById($row['assessmentId']);
                $user = $userMapper->findById($row['userId']);
                $projectCache->refresh($assessmentMapperProxy, $quiz, $user);

                // Attempt cleaning student / quiz variables, to reduce memory leaks...
                unset($quiz, $user);
            } else {
                $this->getLogger()->info(sprintf("Planning to update quiz attempt: %s for user: %s, and project results", $row['assessmentId'], $row['userId']));
            }
        }
    }

    private function updateAttempt(string $attemptID)
    {
        $db     = $this->getDatabaseInternal();
        $query = $db->prepareQuery("UPDATE assess_attempt,
        (SELECT
               if((SUM(ar.mark) >= 9999999999999.99), if((SUM(aq.marks) >= 9999999999999.99), 9999999999999.99, SUM(aq.marks)), SUM(ar.mark))  AS mark,
               if((SUM(aq.marks) >= 9999999999999.99), 9999999999999.99, SUM(aq.marks))  AS max_mark,
               aa.attempt_id
           FROM assess_attempt aa
                    JOIN assess q ON q.assess_id = aa.assess_id
                    LEFT JOIN assess_response ar ON aa.attempt_id  = ar.attempt_id
                    LEFT JOIN assess_question aq ON ar.question_id = aq.question_id
           WHERE aa.attempt_id = :attemptId
             AND ar.deleted_at IS NULL
             AND aq.deleted_at IS NULL
           GROUP BY aa.assess_id, aa.attempt_id) as newVal
        SET
          assess_attempt.max_mark = newVal.max_mark,
          assess_attempt.mark  = newVal.mark,
          assess_attempt.reviewed_at = assess_attempt.finish
        WHERE assess_attempt.attempt_id = newVal.attempt_id;");
        $query->execute(['attemptId' => $attemptID]);
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
