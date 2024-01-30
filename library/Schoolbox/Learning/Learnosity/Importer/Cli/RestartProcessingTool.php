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
use Schoolbox\Learning\Learnosity\TagFactory;
use Schoolbox\Url\Factory;

/**
 * CLI tool for restarting sync of Learnosity data for quizzes in the processing
 * status.
 */
class RestartProcessingTool extends CliTool\App
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
        $quizQuery->execute(['status' => QuizDomainModel::LEARNOSITY_JOB_STATUS_PROCESSING]);
        $quizIds = $quizQuery->fetchColumnArray('assessment_id');
        $processingQuizzes = $this->assessmentDataSource->findAllByIds($quizIds);

        $this->getLogger()->info(sprintf('Found %d quizzes currently processing', count($processingQuizzes)));
        foreach ($processingQuizzes as $processingQuiz) {
            // Not a quiz somehow? Skip
            if (!$processingQuiz instanceof QuizDomainModel) {
                $this->getLogger()->warning(
                    sprintf(
                        'Assessment #%d (%s) is not a quiz: skipping',
                        $processingQuiz->getTitle(),
                        $processingQuiz->getId(),
                    )
                );
                continue;
            }

            $this->getLogger()->info(
                sprintf(
                    'Force-polling assessment #%d (%s)',
                    $processingQuiz->getId(),
                    $processingQuiz->getTitle(),
                )
            );

            $tagFactory = new TagFactory($this->getConfig()->get('site_school_code'));
            $this->duplicator->pollQuizStatus($processingQuiz, $tagFactory->produceFromAssessment($processingQuiz));
        }
    }
}
