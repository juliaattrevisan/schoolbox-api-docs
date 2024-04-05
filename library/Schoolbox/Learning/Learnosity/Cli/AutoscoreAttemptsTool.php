<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Assessment\Quiz\Model;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Quiz\Attempt\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Url\Factory as UrlFactory;

class AutoscoreAttemptsTool extends CliTool\App
{
    /**
     * Name of this tool's lock.
     */
    public const LOCK_NAME = 'learnosity_autoscore_attempts';

    /**
     * Lock timeout: 60s I guess?
     */
    public const LOCK_TIMEOUT = 60;

    /**
     * Search for quiz attempts which:
     * * are in status `\Schoolbox\Learning\Learnosity\Helper::LEARNOSITY_PROCESS_STATUS_PROCESSING`; or:
     * * optionally are in `null` status, and have not yet been reviewed
     *
     * Then, query Learnosity for whether they have been scored yet: if so, add
     * the mark from Learnosity.
     *
     * @return void
     */
    public function run(): void
    {
        $lock          = $this->acquireLock(self::LOCK_NAME, self::LOCK_TIMEOUT);
        $attemptMapper = Mapper::getInstance();

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
            $this->getLogger()->notice(sprintf('No quiz attempts require auto-scoring: nothing to do'));
            $lock->release();
            return;
        }

        // Otherwise, start processing
        $this->getLogger()->notice(sprintf('%d quiz attempts require auto-scoring', count($ids)));
        $api = new Api(
            (new UrlFactory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger()
        );
        $helper = (new Helper($api, $this->getLogger()));

        /** @var AssessmentMapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        // Now find attempts
        $attempts  = $attemptMapper->findByColumn('attempt_id', $ids);
        $quizCache = [];

        // And process them
        foreach ($attempts as $attempt) {
            $lock->refresh(self::LOCK_TIMEOUT);
            $quizId = $attempt->getQuiz()->getAssessmentModel()->getId();
            $this->getLogger()->notice(sprintf('Processing attempt #%d for quiz #%d', $attempt->getId(), $quizId));
            try {
                $quizCache[$quizId] ??= $assessmentDataSource->findById($quizId);
            } catch (MapperException $e) {
                $this->getLogger()->warning(
                    sprintf('Quiz #%d not found (error: %s): skipping', $quizId, $e->getMessage()),
                );
                continue;
            }
            if (!$quizCache[$quizId] instanceof Model) {
                $this->getLogger()->warning(
                    sprintf('Quiz with assessment ID #%d is somehow not a quiz: skipping', $quizId),
                );
                continue;
            }
            $helper->processAttemptCompletion(
                $attemptMapper,
                $quizCache[$quizId],
                $attempt,
            );
            match ($attempt->getLearnosityStatus()) {
                Helper::LEARNOSITY_PROCESS_STATUS_PENDING => $this->getLogger()->warning(
                    sprintf('Attempt #%d is somehow pending: should be impossible', $attempt->getId()),
                ),
                Helper::LEARNOSITY_PROCESS_STATUS_PROCESSING => $this->getLogger()->debug(
                    sprintf('Attempt #%d is still processing', $attempt->getId()),
                ),
                Helper::LEARNOSITY_PROCESS_STATUS_COMPLETED => $this->getLogger()->notice(
                    sprintf('Attempt #%d has been processed: scores have been loaded', $attempt->getId()),
                ),
                Helper::LEARNOSITY_PROCESS_STATUS_FAILED => $this->getLogger()->warning(
                    sprintf('Attempt #%d has failed to process', $attempt->getId()),
                ),
                Helper::LEARNOSITY_PROCESS_STATUS_UNPROCESSABLE => $this->getLogger()->warning(
                    sprintf('Attempt #%d has been determined to be unprocessable', $attempt->getId()),
                ),
                default => $this->getLogger()->warning(
                    sprintf(
                        'Attempt #%d has unknown status (%s): should be impossible',
                        $attempt->getId(),
                        $attempt->getLearnosityStatus(),
                    ),
                ),
            };
        }

        $this->getLogger()->notice('Auto-scoring finished.');
        $lock->release();
    }
}
