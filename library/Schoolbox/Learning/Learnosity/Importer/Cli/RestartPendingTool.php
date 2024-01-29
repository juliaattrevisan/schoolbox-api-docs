<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Interop\Queue\Exception as InteropQueueException;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Quiz;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Factory;

/**
 * CLI tool for restarting sync of Learnosity data for quizzes in the pending
 * status.
 */
class RestartPendingTool extends CliTool\App
{
    /**
     * Duplicator for quiz data.
     */
    private Api\Duplicator $duplicator;

    /**
     * Data source for assessments.
     */
    private MapperProxy $assessmentDataSource;

    public function run(): void
    {
        $baseUrl = (new Factory($this->getConfig()))->buildFullyQualified();

        // Build duplicator
        $this->duplicator = new Api\Duplicator(
            new Api(
                $baseUrl->getHost(),
                $this->getConfig()->get('learnosity_version'),
                $this->getConfig()->get('learnosity_consumer_key'),
                $this->getConfig()->get('learnosity_consumer_secret'),
                $this->getLogger(),
            ),
            $this->getDatabaseInternal(),
            $baseUrl,
            $this->getDi()->get('httpFactory'),
            $this->getDi()->get('router.generator'),
            $this->getDi()->get('taskd.httpRequestMessageFactory'),
            $this->getDi()->get('taskd.messageProducer'),
        );

        // Build assessment data source
        $this->assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        // Find pending quiz domain models
        $quizQuery = $this->getDatabaseInternal()->prepareQuery(
            "SELECT assessment_id FROM assess WHERE learnosity_job_status = :status",
        );
        $quizQuery->execute(['status' => Quiz\Model::LEARNOSITY_JOB_STATUS_PENDING]);
        $quizIds = $quizQuery->fetchColumnArray('assessment_id');
        $pendingQuizzes = $this->assessmentDataSource->findAllByIds($quizIds);

        $this->getLogger()->info(sprintf('Found %d quizzes currently pending copy', count($pendingQuizzes)));
        foreach ($pendingQuizzes as $pendingQuiz) {
            // Not a quiz somehow? Skip
            if (!$pendingQuiz instanceof QuizDomainModel) {
                $this->getLogger()->warning(
                    sprintf(
                        'Assessment #%d (%s) is not a quiz: skipping',
                        $pendingQuiz->getTitle(),
                        $pendingQuiz->getId(),
                    )
                );
                continue;
            }

            $this->getLogger()->info(
                sprintf(
                    'Force-copying assessment #%d (%s)',
                    $pendingQuiz->getId(),
                    $pendingQuiz->getTitle(),
                )
            );

            // Does this quiz already have a Learnosity source assessment?
            // If so, use that
            try {
                if ($pendingQuiz->getLearnositySourceAssessmentId()) {
                    $sourceQuiz = $this->findFromLearnositySourceAssessment($pendingQuiz);
                } elseif ($pendingQuiz->isImplementation()) {
                    $sourceQuiz = $this->findFromSpecificationAssessment($pendingQuiz);
                } elseif ($pendingQuiz->isSpecification()) {
                    $sourceQuiz = $this->findFromImplementationAssessment($pendingQuiz);
                } else {
                    // If somehow this isn't an assessment:
                    // mark it as failed
                    throw new SourceAssociationException(
                        sprintf(
                            'Assessment #%d is somehow neither an implementation nor specification: skipping',
                            $pendingQuiz->getId(),
                        )
                    );
                }

                // Restart the copy process
                $this->getLogger()->debug(
                    sprintf(
                        'Assessment #%d has source assessment #%d: copying from that assessment',
                        $pendingQuiz->getId(),
                        $sourceQuiz->getId(),
                    )
                );
                $this->restartCopyProcess($sourceQuiz, $pendingQuiz);
            } catch (SourceAssociationException $e) {
                // This pending quiz couldn't be associated with a source:
                // mark it as failed.
                $this->getLogger()->warning(
                    sprintf(
                        'Could not associate assessment with source (message: %s): marking as failed',
                        $e->getMessage(),
                    )
                );
                $pendingQuiz->setLearnosityJobStatus(QuizDomainModel::LEARNOSITY_JOB_STATUS_FAILED);
                $this->assessmentDataSource->save($pendingQuiz, $pendingQuiz->getCreator());
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
        $this->getLogger()->debug(
            sprintf(
                'Assessment #%d has source assessment #%d: copying from that assessment',
                $destination->getId(),
                $destination->getLearnositySourceAssessmentId(),
            )
        );

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
