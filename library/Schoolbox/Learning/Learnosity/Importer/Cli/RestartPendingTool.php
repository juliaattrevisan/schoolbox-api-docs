<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Quiz\Mapper;
use Schoolbox\Learning\Quiz\Model;

class RestartPendingTool extends CliTool\App
{
    public function run(): void
    {
        $quizQuery = $this->getDatabaseInternal()->prepareQuery(
            "SELECT assessment_id FROM assess WHERE learnosity_job_status = :status",
        );
        $quizQuery->execute(['status' => Model::LEARNOSITY_JOB_STATUS_PENDING]);
        $pendingQuizzes = $quizQuery->fetchColumnArray('assessment_id');

        $quizMapper = Mapper::getInstance();
        foreach ($pendingQuizzes as $pendingQuizId) {
            // Is this quiz an implementation? Find its spec
            // Is this quiz a spec? It was copied from an implementation, but
            // which one?
            // May need a separate learnosity_import_log:
            // from, to, reference, job_status, job_reference
        }
    }
}
