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
        $baseUrl = (new Factory($this->getConfig()))->buildFullyQualified();

        // Build Learnosity API
        $api = new Api(
            $baseUrl->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger(),
        );

        // Build duplicator
        $duplicator = new Api\Duplicator(
            $api,
            $this->getDatabaseInternal(),
            $baseUrl,
            $this->getDi()->get('httpFactory'),
            $this->getDi()->get('router.generator'),
            $this->getDi()->get('taskd.httpRequestMessageFactory'),
            $this->getDi()->get('taskd.messageProducer'),
        );

        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');

        // Perform all copies within the context of the Schoolbox user
        $schoolboxUser = $mapperFactory->get(UserMapper::class)->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN);

        // Find pending quiz domain models
        $quizQuery = $this->getDatabaseInternal()->prepareQuery(
            "SELECT assessment_id FROM assess WHERE learnosity_job_status = :status",
        );
        $quizQuery->execute(['status' => Quiz\Model::LEARNOSITY_JOB_STATUS_PENDING]);
        $quizIds = $quizQuery->fetchColumnArray('assessment_id');
        /** @var MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');
        $pendingQuizzes = $assessmentDataSource->findAllByIds($quizIds);

        $this->getLogger()->info(sprintf('Found %d quizzes currently pending copy', count($pendingQuizzes)));
        foreach ($pendingQuizzes as $pendingQuiz) {
            // Not a quiz somehow? Skip
            if (!$pendingQuiz instanceof QuizDomainModel) {
                continue;
            }

            $this->getLogger()->info(
                sprintf(
                    'Force-copying quiz "%s" (assessment #%d)',
                    $pendingQuiz->getTitle(),
                    $pendingQuiz->getId(),
                )
            );

            // Does this quiz already have a Learnosity source assessment?
            // If so, use that
            if ($pendingQuiz->getLearnositySourceAssessmentId()) {
                $this->getLogger()->debug(
                    sprintf(
                        'Assessment #%d has source assessment #%d: copying from that assessment',
                        $pendingQuiz->getId(),
                        $pendingQuiz->getLearnositySourceAssessmentId(),
                    )
                );
                $source = $assessmentDataSource->findById($pendingQuiz->getLearnositySourceAssessmentId());
                if (!$source instanceof QuizDomainModel) {
                    $this->getLogger()->warning(
                        sprintf(
                            'Assessment #%d source assessment #%d is somehow not a quiz: skipping',
                            $pendingQuiz->getId(),
                            $pendingQuiz->getLearnositySourceAssessmentId(),
                        )
                    );
                    continue;
                }
                $duplicator->copyQuizConstituents($source, $pendingQuiz);
                $duplicator->initiatePollingForQuizStatus($schoolboxUser, $pendingQuiz);
                continue;
            }

            // Otherwise, is this quiz an implementation?
            // Find its spec, and copy its constituents
            $this->getLogger()->debug(
                sprintf(
                    'Assessment #%d has no source assessment: deriving source from relations',
                    $pendingQuiz->getId(),
                )
            );
            if ($pendingQuiz->isImplementation()) {
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
                    $duplicator->copyQuizConstituents($specQuiz, $pendingQuiz);
                    $duplicator->initiatePollingForQuizStatus($schoolboxUser, $pendingQuiz);
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
                continue;
            }

            // Is this quiz a spec?
            // It was copied from an implementation, but which one?
            // If there's only one, I can use that; otherwise I cannot tell from
            // the quiz itself.
            if ($pendingQuiz->isSpecification()) {
                $this->getLogger()->debug(
                    sprintf(
                        'Assessment #%d is an specification: source is an implementation',
                        $pendingQuiz->getId(),
                    )
                );
                $implementations = $assessmentDataSource->findAllImplementationsBySpecificationId(
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
                        $duplicator->copyQuizConstituents($implQuiz, $pendingQuiz);
                        $duplicator->initiatePollingForQuizStatus($schoolboxUser, $pendingQuiz);
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
        }
    }
}
