#!/usr/bin/php
<?php

/**
 * Auto-completes quiz attempts which have expired.
 *
 * The attempt completion process usually runs when the student visits
 * `/assess/end/$quizId/$attemptId` - this happens when they click the "Finish"
 * button, or their quiz attempt times out.
 *
 * If the student closes their browser without either of the above happening,
 * the attempt will never be completed. This script finds attempts in the
 * following state:
 * * attempt.finish_time not set
 * * attempt.start_time + attempt.assess.duration + 5 minutes leeway < now
 * and marks them as completed.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Assess\IAutomarkable;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Participant;
use Schoolbox\MapperFactory;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App{

    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Auto-completes quiz attempts which have expired; users sometimes forget to officially
finish their attempts so we do it for them when the quiz expires.

This might have been installed as a cron script so it runs periodically.

Usage: autoFinish.php


HELP;
            exit;
        }

        /**
         * @var MapperFactory      $mapperFactory
         * @var Participant\Mapper $participantMapper
         */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        $participantMapper = $mapperFactory->get(Participant\Mapper::class);
        $log             = $this->getLogger();
        $attemptMapper   = QuizAttemptMapper::getInstance();
        $responseHandler = new Assess_Response();

        $expiredAttempts = $attemptMapper->findUnfinishedExpiredAttempts();
        $updatedAttempts = [];

        $log->notice(sprintf("Processing %d expired attempts", count($expiredAttempts)));
        foreach ($expiredAttempts as $attempt) {
            $log->notice(sprintf("Processing attempt #%d...", $attempt->getId()));
            $log->info(
                sprintf(
                    "Attempt quiz #%d (%s), homepage #%d",
                    $attempt->getQuiz()->getId(),
                    $attempt->getQuiz()->getTitle(),
                    $attempt->getQuiz()->getCategory()->getId()
                )
            );
            $log->info(
                sprintf(
                    "Attempt owner #%d (%s)",
                    $attempt->getUser()->getId(),
                    $attempt->getUser()->formatName()
                )
            );
            if (!$attempt->getUser()->getId()) {
                $log->warning('For some reason, this attempt has no owner - skipping');
                continue;
            }

            $participant = $participantMapper->findForAssessmentAndUser(
                $attempt->getQuiz()->getAssessmentModel()->getId(),
                $attempt->getUser()->getId()
            );
            if ($participant && $participant->isExcluded()) {
                $log->info('Attempt owner is excluded from this assessment - skipping');
                continue;
            }

            // Figure out what the finish date ought to be.
            // If it's less than 5 minutes ago, allow some leeway.
            $attemptFinishDate = clone $attempt->getStart();
            $attemptFinishDate = $attemptFinishDate->addSeconds($attempt->getQuiz()->getDuration());
            if (Alaress_Date_Format::now()->diffInSeconds($attemptFinishDate) <= 300) {
                $log->warning(
                    sprintf(
                        "Finish time %s is less than 5 minutes ago - skipping this attempt",
                        $attemptFinishDate->getDatabase()
                    )
                );
            } else {
                $log->notice(sprintf("Setting finish time to %s", $attemptFinishDate->getDatabase()));
                $attempt->setFinish($attemptFinishDate);
                $updatedAttempts[] = $attempt;

                /* Automark the attempt so its ready for review */
                $factory = new Assess_Factory();

                $allResponses = $responseHandler->getAutomarkableResponses($attempt->getId());
                $markedResponseIDs = array();

                if (is_array($allResponses)) {
                    $log->notice(sprintf("Automarking attempt #%s", $attempt->getId()));
                    foreach ($allResponses as $currentResponse) {
                        /** @var IAutomarkable $answerHandler */
                        $answerHandler = $factory->createAnswerModel($currentResponse['type_id']);

                        $answerHandler->mark($currentResponse);

                        $markedResponseIDs[] = $currentResponse['response_id'];
                    }
                    $responseHandler->updatedAutomatedMarks($markedResponseIDs);
                }
            }
        }

        if (count($updatedAttempts) > 0) {
            $log->notice(sprintf("Saving %d expired attempts", count($updatedAttempts)));
            $attemptMapper->save($updatedAttempts);
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
