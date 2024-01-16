<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Quiz;
use Schoolbox\MapperFactory;

/**
 * CLI tool for restarting sync of Learnosity data for quizzes in the pending
 * status.
 */
class RestartPendingTool extends CliTool\App
{
    public function run(): void
    {
        $quizQuery = $this->getDatabaseInternal()->prepareQuery(
            "SELECT assessment_id FROM assess WHERE learnosity_job_status = :status",
        );
        $quizQuery->execute(['status' => Quiz\Model::LEARNOSITY_JOB_STATUS_PENDING]);
        $pendingQuizzes = $quizQuery->fetchColumnArray('assessment_id');


        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        $quizMapper    = $mapperFactory->get(Quiz\Mapper::class);

        /** @var MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        $pendingQuizzes = $assessmentDataSource->findAllByIds($pendingQuizzes);
        foreach ($pendingQuizzes as $pendingQuiz) {
            // Is this quiz an implementation?
            // Find its spec, and copy its constituents
            if ($pendingQuiz->isImplementation()) {
                $specQuiz = $pendingQuiz->getSpecificationAssessment();
                $quizMapper->copyQuizConstituents(
                    $quizMapper->findOneByColumn('assessment_id', $specQuiz->getId()),
                    $quizMapper->findOneByColumn('assessment_id', $pendingQuiz->getId()),
                );
            }

            // Is this quiz a spec?
            // It was copied from an implementation, but which one?
            // I cannot tell from the quiz itself: I'll need to store the source
            // assessment ID somehow, or even a separate learnosity_import_log:
            // from, to, reference, job_status, job_reference
        }
    }
}
