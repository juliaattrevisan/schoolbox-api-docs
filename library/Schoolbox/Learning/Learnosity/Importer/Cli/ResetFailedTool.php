<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * CLI tool for resetting sync of Learnosity data for quizzes in the failed
 * status.
 *
 * This will set their status back to pending, and clear their job reference.
 */
class ResetFailedTool extends BaseTool
{
    /**
     * Name of this tool's lock.
     */
    public const LOCK_NAME = 'learnosity_reset_failed';

    /**
     * Expected maximum seconds for this lock to be held per iteration of
     * `processQuiz`.
     *
     * This allows 30 seconds for the setting of a quiz from failed to pending,
     * but shouldn't need that long.
     */
    private const LOCK_TIMEOUT = 30;

    /**
     * This tool works upon quizzes currently being processed.
     */
    protected function getTargetedQuizStatus(): string
    {
        return Helper::LEARNOSITY_PROCESS_STATUS_FAILED;
    }

    /**
     * This tool processes `$quiz` by setting it back to pending, and clearing
     * its Learnosity job reference,
     *
     * @param QuizDomainModel $quiz
     * @return void
     * @throws MapperException
     */
    protected function processQuiz(QuizDomainModel $quiz): void
    {
        if ($this->perform()) {
            $quiz
                ->setLearnosityJobStatus(Helper::LEARNOSITY_PROCESS_STATUS_PENDING)
                ->setLearnosityJobReference(null);
            $this->assessmentDataSource->save($quiz, $quiz->getCreator());
        }
    }

    /**
     * @return string
     */
    protected function getToolName(): string
    {
        return 'Reset failed quiz duplication tool';
    }

    /**
     * @return string
     */
    protected function getLockName(): string
    {
        return self::LOCK_NAME;
    }

    /**
     * @return int
     */
    protected function getLockTimeout(): int
    {
        return self::LOCK_TIMEOUT;
    }

    /**
     * @return string
     */
    protected function getToolDescription(): string
    {
        return <<<DESC
    Resets any failed quiz duplication jobs.

    These jobs will be set back to the pending status, and their job reference will be cleared;
    note this does not address any possible underlying cause for the failure.
DESC;
    }
}
