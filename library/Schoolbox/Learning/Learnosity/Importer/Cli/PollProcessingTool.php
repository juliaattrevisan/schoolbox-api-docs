<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;

/**
 * CLI tool for polling quizzes in the processing status.
 */
class PollProcessingTool extends BaseTool
{
    /**
     * This tool works upon quizzes currently being processed.
     */
    protected function getTargetedQuizStatus(): string
    {
        return QuizDomainModel::LEARNOSITY_JOB_STATUS_PROCESSING;
    }

    /**
     * This tool processes `$quiz` by polling Learnosity for its job's status,
     * and marks its Learnosity status as:
     * * complete, if the job is complete
     * * failed, if the job is halted
     * * unchanged otherwise
     *
     * @param QuizDomainModel $quiz
     * @return void
     */
    protected function processQuiz(QuizDomainModel $quiz): void
    {
        $this->getLogger()->info(
            sprintf(
                'Force-polling assessment #%d (%s)',
                $quiz->getId(),
                $quiz->getTitle(),
            )
        );

        if ($this->perform()) {
            $this->duplicator->pollQuizStatus($quiz);
            $newStatus = $quiz->getLearnosityJobStatus();
            match ($newStatus) {
                QuizDomainModel::LEARNOSITY_JOB_STATUS_COMPLETED => $this->getLogger()->info(
                    sprintf('Assessment #%d successfully processed', $quiz->getId())
                ),
                QuizDomainModel::LEARNOSITY_JOB_STATUS_PROCESSING => $this->getLogger()->info(
                    sprintf('Assessment #%d still processing', $quiz->getId())
                ),
                QuizDomainModel::LEARNOSITY_JOB_STATUS_FAILED => $this->getLogger()->warning(
                    sprintf('Assessment #%d processing failed', $quiz->getId())
                ),
                QuizDomainModel::LEARNOSITY_JOB_STATUS_PENDING => $this->getLogger()->warning(
                    sprintf('Assessment #%d somehow pending: should be impossible', $quiz->getId())
                ),
                default => $this->getLogger()->warning(
                    sprintf(
                        'Assessment #%d unknown status (%s): should be impossible',
                        $quiz->getId(),
                        $newStatus,
                    )
                ),
            };
        }
    }

    /**
     * @return string
     */
    protected function getToolName(): string
    {
        return 'Poll processing quiz duplication tool';
    }

    /**
     * @return string
     */
    protected function getToolDescription(): string
    {
        return <<<DESC
    Poll any quiz duplication jobs currently processing.

    This tool will manually poll Learnosity for the status of the quiz's duplication job:
    * if it is finished, it will mark the quiz as complete, and set its tags
    * if it has an error, it will mark it as failed
    * if it hasn't finished yet, it will not be changed
DESC;
    }
}
