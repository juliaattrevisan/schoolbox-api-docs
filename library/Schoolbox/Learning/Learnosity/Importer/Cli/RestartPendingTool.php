<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Quiz;
use Schoolbox\MapperFactory;
use Schoolbox\Url\Factory;
use Schoolbox\UserMapper;

/**
 * CLI tool for restarting sync of Learnosity data for quizzes in the pending
 * status.
 */
class RestartPendingTool extends CliTool\App
{
    public function run(): void
    {
        // Build Learnosity API
        $api = new Api(
            (new \Schoolbox\Url\Factory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger(),
        );

        // Build duplicator
        $duplicator = new Api\Duplicator(
            $api,
            $this->getDatabaseInternal(),
            (new Factory($this->getConfig()))->buildFullyQualified(),
            $this->getDi()->get('httpFactory'),
            $this->getDi()->get('router.generator'),
            $this->getDi()->get('taskd.httpRequestMessageFactory'),
            $this->getDi()->get('taskd.messageProducer'),
        );

        // Find pending quizzes
        $quizQuery = $this->getDatabaseInternal()->prepareQuery(
            "SELECT assessment_id FROM assess WHERE learnosity_job_status = :status",
        );
        $quizQuery->execute(['status' => Quiz\Model::LEARNOSITY_JOB_STATUS_PENDING]);

        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        $quizMapper    = $mapperFactory->get(Quiz\Mapper::class);

        // Perform all copies within the context of the Schoolbox user
        $schoolboxUser = $mapperFactory->get(UserMapper::class)->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN);

            /** @var MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        /** @var Quiz\Model[] $pendingQuizzes */
        $pendingQuizzes = $quizMapper->findByColumn(
            'learnosity_job_status',
            Quiz\Model::LEARNOSITY_JOB_STATUS_PENDING,
        );
        $this->getLogger()->info(sprintf('Found %d quizzes currently pending copy', count($pendingQuizzes)));
        foreach ($pendingQuizzes as $pendingQuiz) {
            $pendingQuizDomainModel = $assessmentDataSource->findById($pendingQuiz->getAssessmentModel()->getId());

            // Does this quiz have a source quiz? If so use it
            $this->getLogger()->info(
                sprintf(
                    'Force-copying quiz "%s" (assessment #%d)',
                    $pendingQuiz->getTitle(),
                    $pendingQuiz->getAssessmentModel()->getId(),
                )
            );
            if ($pendingQuiz->getLearnositySourceAssessmentId()) {
                $this->getLogger()->debug(
                    sprintf(
                        'Assessment #%d has source assessment #%d: copying from that assessment',
                        $pendingQuiz->getAssessmentModel()->getId(),
                        $pendingQuiz->getLearnositySourceAssessmentId(),
                    )
                );
                $source = $assessmentDataSource->findById($pendingQuiz->getLearnositySourceAssessmentId());
                $duplicator->copyQuizConstituents($source, $pendingQuizDomainModel);

                // Tell taskd to start polling
                $duplicator->initiatePollingForQuizStatus($schoolboxUser, $pendingQuizDomainModel);
                continue;
            }

            // Otherwise, is this quiz an implementation?
            // Find its spec, and copy its constituents
            $this->getLogger()->debug(
                sprintf(
                    'Assessment #%d has no source assessment: deriving source from relations',
                    $pendingQuiz->getAssessmentModel()->getId(),
                )
            );
            if ($pendingQuizDomainModel->isImplementation()) {
                $this->getLogger()->debug(
                    sprintf(
                        'Assessment #%d is an implementation: source is specification',
                        $pendingQuiz->getAssessmentModel()->getId(),
                    )
                );
                $specQuiz = $pendingQuizDomainModel->getSpecificationAssessment();
                if ($specQuiz) {
                    $this->getLogger()->debug(
                        sprintf(
                            'Assessment #%d has spec assessment #%d: copying from that assessment',
                            $pendingQuiz->getAssessmentModel()->getId(),
                            $specQuiz->getId(),
                        )
                    );
                    $duplicator->copyQuizConstituents($specQuiz, $pendingQuizDomainModel);

                    // Tell taskd to start polling
                    $duplicator->initiatePollingForQuizStatus($schoolboxUser, $pendingQuizDomainModel);
                } else {
                    $this->getLogger()->warning(
                        sprintf(
                            'Assessment #%d is an implementation with no spec assessment: cannot copy',
                            $pendingQuiz->getAssessmentModel()->getId(),
                        )
                    );
                }
                continue;
            }

            // Is this quiz a spec?
            // It was copied from an implementation, but which one?
            // If there's only one, I can use that; otherwise I cannot tell from
            // the quiz itself.
            if ($pendingQuizDomainModel->isSpecification()) {
                $this->getLogger()->debug(
                    sprintf(
                        'Assessment #%d is an specification: source is an implementation',
                        $pendingQuiz->getAssessmentModel()->getId(),
                    )
                );
                $implementations = $assessmentDataSource->findAllImplementationsBySpecificationId(
                    $pendingQuizDomainModel->getId(),
                );
                $implementationsCount = count($implementations);
                if ($implementationsCount === 1) {
                    /** @var IAssessable $implQuiz */
                    $implQuiz = $implementations->first();
                    $this->getLogger()->debug(
                        sprintf(
                            'Assessment #%d has singular implementation assessment #%d: copying from that assessment',
                            $pendingQuiz->getAssessmentModel()->getId(),
                            $implQuiz->getId(),
                        )
                    );
                    $duplicator->copyQuizConstituents($implQuiz, $pendingQuizDomainModel);

                    // Tell taskd to start polling
                    $duplicator->initiatePollingForQuizStatus($schoolboxUser, $pendingQuizDomainModel);
                } else {
                    $this->getLogger()->warning(
                        sprintf(
                            'Assessment #%d is a spec with %s implementations: cannot copy',
                            ($implementationsCount === 0 ? 'no' : 'multiple'),
                            $pendingQuiz->getAssessmentModel()->getId(),
                        )
                    );
                }
            }
        }
    }
}
