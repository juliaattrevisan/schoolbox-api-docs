<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Quiz\Attempt\Mapper;

class AutoscoreAttemptsTool extends CliTool\App
{
    /**
     * Name of this tool's lock.
     */
    public const LOCK_NAME = 'learnosity_autoscore_attempts';

    /**
     * Search for quiz attempts which:
     * * are completed
     * * have a `null` mark
     * * are in status `\Schoolbox\Learning\Learnosity\Helper::LEARNOSITY_PROCESS_STATUS_PROCESSING`
     * * optionally, have a `null` status
     *
     * Then, query Learnosity for whether they have been scored yet: if so, add
     * the mark from Learnosity.
     *
     * @return void
     */
    public function run(): void
    {
        $api = new Api(
            (new \Schoolbox\Url\Factory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger()
        );

        $helper = (new Helper($api, $this->getLogger()));

        $attemptMapper = Mapper::getInstance();

        /** @var AssessmentMapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        // Find quiz attempts which are still processing
        $baseQuery = "
            SELECT aa.attempt_id
            FROM assess_attempt aa
            INNER JOIN assess a ON aa.assess_id = a.assess_id
            INNER JOIN assessment a2 on a.assessment_id = a2.id
        ";
        $pb = $attemptMapper->getPredicateBuilder();
        $pb = $pb->notNull('aa.finish');

        $statusPb = $attemptMapper->getPredicateBuilder();
        $statusPb = $statusPb->equals('aa.learnosity_status', Helper::LEARNOSITY_PROCESS_STATUS_PROCESSING);

        // If specially requested: process null Learnosity status as well
        $statusPb = $statusPb->isNull('aa.learnosity_status');

        // Now build and run query
        $pb = $pb->any($statusPb);
        $query = $this->getDatabaseInternal()->prepareQuery(
            $baseQuery . ' WHERE ' . $pb->getWhereIntersect(),
        );
        $query->execute($pb->getParams());

        $ids = $query->fetchColumn();
        if (empty($ids)) {
            // Nothing to do
            return;
        }

        // Otherwise:
        $attempts = $attemptMapper->findByColumn('attempt_id', $ids);
        foreach ($attempts as $attempt) {
            $helper->processAttemptCompletion(
                $attemptMapper,
                $assessmentDataSource->findById($attempt->getQuiz()->getAssessmentModel()->getId()),
                $attempt,
            );
        }
    }
}
