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
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Assessment\Course\Task;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
$kernel = new \Schoolbox\Application\Daemon\Kernel("Quiz Attempt Automatic Closer", array('level'=>200));

$log             = $kernel->logger();
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
