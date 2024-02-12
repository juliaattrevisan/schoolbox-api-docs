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
use Schoolbox\Mapper\Exception as MapperException;

/**
 * CLI tool for restarting sync of Learnosity data for quizzes in the pending
 * status.
 */
class RestartPendingTool extends BaseTool
{
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    /**
     * Short options:
     * * -h: help
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /**
     * Long options:
     * * --help:  help
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP];
    }

    /**
     * @param QuizDomainModel $quiz
     * @return void
     * @throws MapperException
     * @throws MapperException\Deleted
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
                $sourceQuiz = $this->findFromSpecificationAssessment($quiz);
            } elseif ($quiz->isSpecification()) {
                $sourceQuiz = $this->findFromImplementationAssessment($quiz);
            } else {
                // If somehow this isn't an assessment:
                // mark it as failed
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
        } catch (SourceAssociationException $e) {
            // This pending quiz couldn't be associated with a source:
            // mark it as failed.
            $this->getLogger()->warning(
                sprintf(
                    'Could not associate assessment with source (message: %s): marking as failed',
                    $e->getMessage(),
                )
            );
            if ($this->perform()) {
                $quiz->setLearnosityJobStatus(QuizDomainModel::LEARNOSITY_JOB_STATUS_FAILED);
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
     * Print help text
     *
     * @return void
     */
    protected function printHelp(): void
    {
        $file       = $this->getToolFilename();
        $green      = self::COLOUR_GREEN;
        $white      = self::COLOUR_WHITE;
        $yellow     = self::COLOUR_YELLOW;
        echo <<<HELP
NAME:
    Restart pending quiz duplication tool

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Attempts to restart any pending quiz duplication jobs.

    A quiz duplication will be pending if:
    * it has been either imported from a course to a class, or exported from a class back into its course, and;
    * it has not been marked as processing yet (i.e. it does not have a Learnosity job reference)

    This tool will:
    * attempt to find the source quiz from which each pending quiz was copied from
    * create a new job in Learnosity for copying the contents of the source quiz into the pending quiz
    * set the quiz's status to "processing"
    * initiate polling for the status of the Learnosity job: when it is finished, it will be marked as complete

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        Actually restart duplication jobs: otherwise, only output what would be restarted.


HELP;
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
    private function findFromSpecificationAssessment(QuizDomainModel $destination): QuizDomainModel
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
    private function findFromImplementationAssessment(QuizDomainModel $destination): QuizDomainModel
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
