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

$app = new class extends CliTool\App
{
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
//        $responseHandler = new Assess_Response();

        $expiredAttempts = $attemptMapper->findUnfinishedExpiredAttempts();
        $updatedAttempts = [];

        /* Look for quiz attempts that should have expired before this time
         * Potentially parameterise this
         */
        $expiry = \Alaress_Date_Format::now();

        /* Mappers we need
         */
        $quizMapper = \Schoolbox\Learning\Quiz\Mapper::getInstance();

        /* Find the assessments which have attempts that need to be shut
         *     - Only process living attempts (ignore deleted attempts)
         *     - ...which haven't already finished
         *     - ...which are associated with living quizzes (ignore deleted assess and assessment rows)
         *     - ...which also have living users (ignore attempts by deleted users)
         *     - ...which can actually expire (ignore quizzes set to Unlimited duration)
         *     - ...which should have already expired, but haven't
         *     - ...which are for Quizzes specifically (ignore survey attempts)
         *
         * This won't take into account quizzes that were reviewed but not finished;
         * this would occur only by direct intervention or hideous mistake
         */
        $query = "
            SELECT assess.assessment_id
            FROM assess_attempt
            INNER JOIN assess ON assess_attempt.assess_id = assess.assess_id
            INNER JOIN assessment ON assess.assessment_id = assessment.id
            INNER JOIN `user` u ON assess_attempt.user_id = u.id
            WHERE assess_attempt.deleted_at IS NULL
            AND assess_attempt.finish IS NULL
            AND assessment.deleted_at IS NULL
            AND u.deleted_at IS NULL
            AND assess.duration > :unlimitedDuration
            AND TIMESTAMPADD(SECOND, `assess`.`duration`, `assess_attempt`.`start`) < :expiry
            ORDER BY assess.assessment_id
        ";

        $statement = $this->getDatabaseInternal()->prepareQuery($query);
        $statement->execute([
            'unlimitedDuration' => \Schoolbox\Learning\Assessment\Quiz\Model::DURATION_UNLIMITED,
            'expiry' => $expiry->getDatabase()
        ]);




        foreach ($expiredAttempts as $attempt) {
            $log->info(sprintf(
                'Processing attempt_id %d',
                $attempt->getId(),
            ));

            /* Generic debug announcement for those wanting to dive deeper
             */
            $log->debug(sprintf(
                'attempt_id %d by %s (%s) was in quiz "%s" (%s) and homepage %s',
                $attempt->getId(),
                $attempt->getUser()->formatName(),
                $attempt->getUser()->getId(),
                $attempt->getQuiz()->getTitle(),
                $attempt->getQuiz()->getId(),
                $attempt->getQuiz()->getCategory()->getId()
            ));

            /* Sometimes an attempt doesn't have a hydrated user
             * I guess it's broken data somehow
             */
            if (!$attempt->getUser()->getId()) {
                $log->warning('For some reason, this attempt has no owner - skipping');
                continue;
            }

            /* I guess this attempt needs its stuff
             * Shank out an $assessment and extract its quiz, caching it locally for performance
             *
             * Note:
             * Quiz mapper's closeExpiredAttempts() method will always spawn a Helper and Learnosity Api
             * instance(s) but don't worry, their constructors are hollow and virtually insignificant
             */

            /* First, we need an assessment via findByIdForReview()
             * This gives us a fully hydrated quiz for a particular user
             */
//            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId'], $student);

            /* Extract the quiz itself, and hydrate it with its attempts and answers
             * For some reason this is a thing
             */




//            /* Check for participant exclusion; if excluded, skip this row
//             */
//            $participant = $participantMapper->findForAssessmentAndUser(
//                $attempt->getQuiz()->getAssessmentModel()->getId(),
//                $attempt->getUser()->getId()
//            );
//
//            if ($participant && $participant->isExcluded()) {
//                $log->info('Attempt owner is excluded from this assessment - skipping');
//                continue;
//            }
//
//            // Figure out what the finish date ought to be.
//            // If it's less than 5 minutes ago, allow some leeway.
//            $attemptFinishDate = clone $attempt->getStart();
//            $attemptFinishDate = $attemptFinishDate->addSeconds($attempt->getQuiz()->getDuration());
//
//
//
//            if (Alaress_Date_Format::now()->diffInSeconds($attemptFinishDate) <= 300) {
//                $log->warning(
//                    sprintf(
//                        "Finish time %s is less than 5 minutes ago - skipping this attempt",
//                        $attemptFinishDate->getDatabase()
//                    )
//                );
//            } else {
//                $log->notice(sprintf("Setting finish time to %s", $attemptFinishDate->getDatabase()));
//                $attempt->setFinish($attemptFinishDate);
//                $updatedAttempts[] = $attempt;
//
//                /* Automark the attempt so its ready for review */
//                $factory = new Assess_Factory();
//
//                $allResponses = $responseHandler->getAutomarkableResponses($attempt->getId());
//                $markedResponseIDs = array();
//
//                if (is_array($allResponses)) {
//                    $log->notice(sprintf("Automarking attempt #%s", $attempt->getId()));
//                    foreach ($allResponses as $currentResponse) {
//                        /** @var IAutomarkable $answerHandler */
//                        $answerHandler = $factory->createAnswerModel($currentResponse['type_id']);
//
//                        $answerHandler->mark($currentResponse);
//
//                        $markedResponseIDs[] = $currentResponse['response_id'];
//                    }
//                    $responseHandler->updatedAutomatedMarks($markedResponseIDs);
//                }
//            }
        }
//
//        if (count($updatedAttempts) > 0) {
//            $log->notice(sprintf("Saving %d expired attempts", count($updatedAttempts)));
//            $attemptMapper->save($updatedAttempts);
//        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]);
$kernel->runApp();
