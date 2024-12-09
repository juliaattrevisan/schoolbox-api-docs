<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Interop\Queue\Exception as InteropQueueException;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * CLI tool for restarting sync of Learnosity data for quizzes in the pending
 * status.
 */
class RestartPendingTool extends BaseTool
{
    /**
     * Name of this tool's lock.
     */
    public const LOCK_NAME = 'learnosity_restart_pending';

    /**
     * Expected maximum seconds for this lock to be held per iteration of
     * `processQuiz`.
     *
     * This allows 3 minutes for the duplicate quiz process to be kicked off.
     */
    private const LOCK_TIMEOUT = 180;

    /**
     * This tool works upon pending quizzes.
     */
    protected function getTargetedQuizStatus(): string
    {
        return Helper::LEARNOSITY_PROCESS_STATUS_PENDING;
    }

    /**
     * This tool processes `$quiz` by finding its source assessment if possible,
     * and then triggering a copy from its source into `$quiz`.
     *
     * @param QuizDomainModel $quiz
     * @return void
     * @throws MapperException
     */
    protected function processQuiz(QuizDomainModel $quiz): void
    {
        $this->getLogger()->info(
            sprintf(
                'Force-copying assessment #%d (%s)',
                $quiz->getId(),
                $quiz->getTitle(),
            )
        );

        // Does this quiz already have a Learnosity source assessment?
        // If so, use that
        try {
            if ($quiz->getLearnositySourceAssessmentId()) {
                $sourceQuiz = $this->findFromLearnositySourceAssessment($quiz);
            } elseif ($quiz->isImplementation()) {
                $sourceQuiz = $this->findFromImplementationSpecification($quiz);
            } elseif ($quiz->isSpecification()) {
                $sourceQuiz = $this->findFromSpecificationImplementations($quiz);
            } else {
                // If somehow this isn't an assessment:
                // mark it as unprocessable
                throw new SourceAssociationException(
                    sprintf(
                        'Assessment #%d is somehow neither an implementation nor specification: skipping',
                        $quiz->getId(),
                    )
                );
            }

            // Restart the copy process
            $this->getLogger()->debug(
                sprintf(
                    'Assessment #%d has source assessment #%d: copying from that assessment',
                    $quiz->getId(),
                    $sourceQuiz->getId(),
                )
            );
            if ($this->perform()) {
                $this->restartCopyProcess($sourceQuiz, $quiz);
            }
        } catch (SourceAssociationException | CopyMissingQuizException $e) {
            // This pending quiz couldn't be associated with a source:
            // mark it as unprocessable.
            $this->getLogger()->warning(
                sprintf(
                    'Could not associate assessment with source (message: %s): marking as unprocessable',
                    $e->getMessage(),
                )
            );
            if ($this->perform()) {
                $quiz->setLearnosityJobStatus(Helper::LEARNOSITY_PROCESS_STATUS_UNPROCESSABLE);
                $this->assessmentDataSource->save($quiz, $quiz->getCreator());
            }
        } catch (InteropQueueException $e) {
            // Something went wrong when sending message to taskd:
            // this is likely a transient error, try again later
            $this->getLogger()->warning(
                sprintf(
                    'Possible error occurred when sending to task daemon: %s',
                    $e->getMessage(),
                ),
            );
        } catch (ValidationException $e) {
            // Something went wrong when sending message to Learnosity:
            // this is likely a transient error, try again later
            $this->getLogger()->warning(
                sprintf(
                    'Possible error occurred when sending to Learnosity: %s',
                    $e->getMessage(),
                ),
            );
        }
    }

    /**
     * @return string
     */
    protected function getToolName(): string
    {
        return 'Restart pending quiz duplication tool';
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
    Attempts to restart any pending quiz duplication jobs.

    A quiz duplication will be pending if:
    * it has been either imported from a course to a class, or exported from a class back into its course, and;
    * it has not been marked as processing yet (i.e. it does not have a Learnosity job reference)

    This tool will:
    * attempt to find the source quiz from which each pending quiz was copied from
    * create a new job in Learnosity for copying the contents of the source quiz into the pending quiz
    * set the quiz's status to "processing"
    * initiate polling for the status of the Learnosity job: when it is finished, it will be marked as complete
DESC;
    }

    /**
     * Find source quiz based on `$destination`'s Learnosity source assessment.
     *
     * This field exists for quizzes copied as of SA-17023.
     *
     * @param QuizDomainModel $destination
     * @return QuizDomainModel
     * @throws SourceAssociationException
     */
    private function findFromLearnositySourceAssessment(QuizDomainModel $destination): QuizDomainModel
    {
        // Find the assessment
        try {
            $source = $this->assessmentDataSource->findById($destination->getLearnositySourceAssessmentId());
        } catch (MapperException\Deleted $e) {
            $source = $e->getDeletedObject();
            $this->getLogger()->debug(
                sprintf(
                    'Source assessment #%d has been deleted (message: %s): still using as source',
                    $destination->getLearnositySourceAssessmentId(),
                    $e->getMessage(),
                )
            );
        } catch (MapperException $e) {
            // Not found at all: raise a warning
            throw new SourceAssociationException(
                sprintf(
                    'Source assessment #%d does not exist: skipping',
                    $destination->getLearnositySourceAssessmentId(),
                ),
                0,
                $e,
            );
        }

        if (!$source instanceof QuizDomainModel) {
            // Somehow not a quiz: raise a warning
            throw new SourceAssociationException(
                sprintf(
                    'Assessment #%d source assessment #%d is somehow not a quiz: skipping',
                    $destination->getId(),
                    $destination->getLearnositySourceAssessmentId(),
                ),
            );
        }

        return $source;
    }

    /**
     * Find source quiz from `$destination`'s specification assessment.
     *
     * This covers the case where an implementation quiz is copied from a spec
     * by importing a course into a class.
     *
     * @param QuizDomainModel $destination
     * @return QuizDomainModel
     * @throws SourceAssociationException
     */
    private function findFromImplementationSpecification(QuizDomainModel $destination): QuizDomainModel
    {
        $this->getLogger()->debug(
            sprintf(
                'Assessment #%d is an implementation: source is specification',
                $destination->getId(),
            )
        );
        $specQuiz = $destination->getSpecificationAssessment();

        // Somehow not a quiz: raise a warning
        if (!$specQuiz instanceof QuizDomainModel) {
            if ($specQuiz) {
                $message = sprintf(
                    'Assessment #%d spec assessment #%d is somehow not a quiz: skipping',
                    $destination->getId(),
                    $specQuiz->getId(),
                );
            } else {
                $message = sprintf(
                    'Assessment #%d is an implementation with no spec assessment: cannot copy',
                    $destination->getId(),
                );
            }
            throw new SourceAssociationException($message);
        }

        return $specQuiz;
    }

    /**
     * Find source quiz from an implementation of `$destination`.
     *
     * This covers the case where an implementation quiz is written back into
     * its spec by copying the quiz from the class back into its course.
     *
     * The search will only succeed if there is one and only one implementation:
     * * if there are no implementations, then there's nothing to copy from
     * * if there's more than one implementation, then it's uncertain which to
     *   copy from so don't even try
     *
     * @param QuizDomainModel $destination
     * @return QuizDomainModel
     * @throws SourceAssociationException
     */
    private function findFromSpecificationImplementations(QuizDomainModel $destination): QuizDomainModel
    {
        $this->getLogger()->debug(
            sprintf(
                'Assessment #%d is an specification: source is an implementation',
                $destination->getId(),
            )
        );

        // Search within deleted implementations:
        // this is because it's viable that the assessment could have been
        // copied from an assessment which is now deleted.
        $implementations      = $this->assessmentDataSource->findAllImplementationsBySpecificationId(
            $destination->getId(),
            ['deleted' => true]
        );
        $implementationsCount = count($implementations);

        // No candidates, or more than one: raise warning
        if ($implementationsCount !== 1) {
            throw new SourceAssociationException(
                sprintf(
                    'Assessment #%d is a spec with %s implementations: cannot copy',
                    ($implementationsCount === 0 ? 'no' : 'multiple'),
                    $destination->getId(),
                ),
            );
        }

        $implQuiz = $implementations->first();

        // Somehow not a quiz: raise warning
        if (!$implQuiz instanceof QuizDomainModel) {
            throw new SourceAssociationException(
                sprintf(
                    'Assessment #%d implementation assessment #%d is somehow not a quiz: skipping',
                    $destination->getId(),
                    $implQuiz->getId(),
                ),
            );
        }

        return $implQuiz;
    }

    /**
     * Actually restart the copy process.
     *
     * @param QuizDomainModel $source      The quiz to copy from.
     * @param QuizDomainModel $destination The quiz to copy into.
     * @return void
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     * @throws \LearnositySdk\Exceptions\ValidationException
     */
    private function restartCopyProcess(QuizDomainModel $source, QuizDomainModel $destination): void
    {
        $this->duplicator->copyQuizConstituents($source, $destination);
        $this->duplicator->initiatePollingForQuizStatus($destination->getCreator(), $destination);
    }
}
