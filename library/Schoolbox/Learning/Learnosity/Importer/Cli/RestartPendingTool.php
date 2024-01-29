<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Quiz;
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
            if ($pendingQuiz->getLearnositySourceAssessmentId()) {
                $this->copyFromLearnositySourceAssessment($pendingQuiz);
                continue;
            }

            $this->getLogger()->debug(
                sprintf(
                    'Assessment #%d has no source assessment: deriving source from relations',
                    $pendingQuiz->getId(),
                )
            );

            if ($pendingQuiz->isImplementation()) {
                $this->copyFromSpecificationAssessment($pendingQuiz);
            } elseif ($pendingQuiz->isSpecification()) {
                $this->copyFromImplementationAssessment($pendingQuiz);
            } else {
                $this->getLogger()->warning(
                    sprintf(
                        'Assessment #%d is somehow neither an implementation nor specification: skipping',
                        $pendingQuiz->getId(),
                    )
                );
            }
        }
    }

    /**
     * Copy Learnosity data from `$pendingQuiz`'s Learnosity source assessment.
     *
     * This field exists for quizzes copied as of SA-17023.
     *
     * @param QuizDomainModel $pendingQuiz
     * @return void
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     * @throws \LearnositySdk\Exceptions\ValidationException
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function copyFromLearnositySourceAssessment(QuizDomainModel $pendingQuiz): void
    {
        $this->getLogger()->debug(
            sprintf(
                'Assessment #%d has source assessment #%d: copying from that assessment',
                $pendingQuiz->getId(),
                $pendingQuiz->getLearnositySourceAssessmentId(),
            )
        );
        $source = $this->assessmentDataSource->findById($pendingQuiz->getLearnositySourceAssessmentId());
        if (!$source instanceof QuizDomainModel) {
            $this->getLogger()->warning(
                sprintf(
                    'Assessment #%d source assessment #%d is somehow not a quiz: skipping',
                    $pendingQuiz->getId(),
                    $pendingQuiz->getLearnositySourceAssessmentId(),
                )
            );
            return;
        }
        $this->restartCopyProcess($source, $pendingQuiz);
    }

    /**
     * Copy Learnosity data from `$pendingQuiz`'s specification assessment.
     *
     * This covers the case where an implementation quiz is copied from a spec
     * by importing a course into a class.
     *
     * @param QuizDomainModel $pendingQuiz
     * @return void
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     * @throws \LearnositySdk\Exceptions\ValidationException
     */
    private function copyFromSpecificationAssessment(QuizDomainModel $pendingQuiz): void
    {
        $this->getLogger()->debug(
            sprintf(
                'Assessment #%d is an implementation: source is specification',
                $pendingQuiz->getId(),
            )
        );
        $specQuiz = $pendingQuiz->getSpecificationAssessment();
        if ($specQuiz instanceof QuizDomainModel) {
            $this->getLogger()->debug(
                sprintf(
                    'Assessment #%d has spec assessment #%d: copying from that assessment',
                    $pendingQuiz->getId(),
                    $specQuiz->getId(),
                )
            );
            $this->restartCopyProcess($specQuiz, $pendingQuiz);
        } elseif ($specQuiz) {
            $this->getLogger()->warning(
                sprintf(
                    'Assessment #%d spec assessment #%d is somehow not a quiz: skipping',
                    $pendingQuiz->getId(),
                    $specQuiz->getId(),
                )
            );
        } else {
            $this->getLogger()->warning(
                sprintf(
                    'Assessment #%d is an implementation with no spec assessment: cannot copy',
                    $pendingQuiz->getId(),
                )
            );
        }
    }

    /**
     * Attempt to copy Learnosity data from an implementation of `$pendingQuiz`.
     *
     * This covers the case where an implementation quiz is written back into
     * its spec by copying the quiz from the class back into its course.
     *
     * The copy will only succeed if there is one and only one implementation:
     * * if there are no implementations, then there's nothing to copy from
     * * if there's more than one implementation, then it's uncertain which to
     *   copy from so don't even try
     *
     * @param QuizDomainModel $pendingQuiz
     * @return void
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     * @throws \LearnositySdk\Exceptions\ValidationException
     */
    private function copyFromImplementationAssessment(QuizDomainModel $pendingQuiz): void
    {
        $this->getLogger()->debug(
            sprintf(
                'Assessment #%d is an specification: source is an implementation',
                $pendingQuiz->getId(),
            )
        );
        $implementations      = $this->assessmentDataSource->findAllImplementationsBySpecificationId(
            $pendingQuiz->getId(),
        );
        $implementationsCount = count($implementations);
        if ($implementationsCount === 1) {
            $implQuiz = $implementations->first();
            $this->getLogger()->debug(
                sprintf(
                    'Assessment #%d has singular implementation assessment #%d: copying from that assessment',
                    $pendingQuiz->getId(),
                    $implQuiz->getId(),
                )
            );
            if ($implQuiz instanceof QuizDomainModel) {
                $this->restartCopyProcess($implQuiz, $pendingQuiz);
            } else {
                $this->getLogger()->warning(
                    sprintf(
                        'Assessment #%d implementation assessment #%d is somehow not a quiz: skipping',
                        $pendingQuiz->getId(),
                        $implQuiz->getId(),
                    )
                );
            }
        } else {
            $this->getLogger()->warning(
                sprintf(
                    'Assessment #%d is a spec with %s implementations: cannot copy',
                    ($implementationsCount === 0 ? 'no' : 'multiple'),
                    $pendingQuiz->getId(),
                )
            );
        }
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
