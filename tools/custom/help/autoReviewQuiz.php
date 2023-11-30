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
                $this->updateAttempt((int)$row['attemptId']);
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

    /**
     * clone of `tools/custom/help/auto-review-quiz.sh` update function to just mark this attempt as complete and give them the automarked score
     *
     * @param int $attemptID
     * @return void
     */
    private function updateAttempt(int $attemptID)
    {
        $db     = $this->getDatabaseInternal();
        $query = $db->prepareQuery("UPDATE assess_attempt
        SET
          assess_attempt.reviewed_at = assess_attempt.finish,
          assess_attempt.reviewer_id = 1
        WHERE assess_attempt.attempt_id = :attemptId;");
        $query->execute(['attemptId' => $attemptID]);
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
