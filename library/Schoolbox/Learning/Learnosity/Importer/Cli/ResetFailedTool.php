<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
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
     * This tool works upon quizzes currently being processed.
     */
    protected function getTargetedQuizStatus(): string
    {
        return QuizDomainModel::LEARNOSITY_JOB_STATUS_FAILED;
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
                ->setLearnosityJobStatus(QuizDomainModel::LEARNOSITY_JOB_STATUS_PENDING)
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
    protected function getToolDescription(): string
    {
        return <<<DESC
    Resets any failed quiz duplication jobs.

    These jobs will be set back to the pending status, and their job reference will be cleared;
    note this does not address any possible underlying cause for the failure.
DESC;
    }
}
