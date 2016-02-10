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

require_once __DIR__ . '/../../include/Bootstrap.php';

use Alaress\Lumberjack\Logger;
use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;
use Schoolbox\Assessment\Course\Task;

$log             = new Logger(STDOUT, null, Monolog\Logger::INFO, [], true);
$attemptMapper   = new AttemptMapper();
$taskMapper      = new Task\InstanceCompletionMapper();
$expiredAttempts = $attemptMapper->findUnfinishedExpiredAttempts();

$log->notice(sprintf("Auto-completing %d expired attempts", count($expiredAttempts)));
foreach ($expiredAttempts as $attempt) {
    $log->notice(sprintf("Auto-completing attempt #%d...", $attempt->getId()));
    $log->info(
        sprintf(
            "Attempt quiz #%d (%s), homepage #%d",
            $attempt->getAssessment()->getId(),
            $attempt->getAssessment()->getTitle(),
            $attempt->getAssessment()->getCategory()->getId()
        )
    );
    $log->info(
        sprintf(
            "Attempt owner #%d (%s)",
            $attempt->getUser()->getId(),
            $attempt->getUser()->formatName()
        )
    );
    // Figure out what the finish date ought to be
    $attemptFinishDate = clone $attempt->getStart();
    $attemptFinishDate = $attemptFinishDate->addSeconds($attempt->getAssessment()->getDuration());
    $log->notice(sprintf("Setting finish time to %s", $attemptFinishDate->getDatabase()));
    $attempt->setFinish($attemptFinishDate);
}

if (count($expiredAttempts) > 0) {
    $log->notice("Saving expired attempts...");
    $attemptMapper->save($expiredAttempts);
}

// Complete associated tasks as well
foreach ($expiredAttempts as $attempt) {
    $taskId = $attempt->getAssessment()->getTaskInstance()->getId();
    if (!empty($taskId)) {
        $log->notice(sprintf("Saving associated task completion for attempt #%d...", $attempt->getId()));
        $taskMapper->markAsCompleted($taskId, $attempt->getUser()->getId());
    }
}
