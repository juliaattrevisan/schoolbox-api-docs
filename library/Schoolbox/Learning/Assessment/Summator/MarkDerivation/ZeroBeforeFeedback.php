<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Summator\MarkDerivation;

use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Task\Model as TaskModel;

/**
 * Strategy for deriving the normalised mark for a participant:
 * a student's mark is zero for an assessment which they have not yet received
 * feedback for.
 */
class ZeroBeforeFeedback implements IStrategy
{
    /**
     * @inheritdoc
     */
    public function deriveNormalisedMark(Participant $participant, bool $publishedOnly): ?float
    {
        $latestFeedback = $participant->getAuthoritativeFeedback($publishedOnly);
        /**
         * Assumptions:
         * - We are in a project
         * - The project is `requireAllAssessments` set to `true`
         * in this special case:
         * - check if we are attempting to view a task
         * if so
         * check if the task has been marked complete by the instructor (learner / student marking does not count here)
         * if the instructor has marked the task complete, then return the NormalisedMark as 100.0,
         * else the task was not marked complete by the instructor so return 0.0 (incomplete)
         */
        if ($participant->getAssessable() instanceof TaskModel) {
            if (Completion::STATUS_TEACHER_ASSESSED_COMPLETE === $participant->findMostRecentResponse()?->getCompletionStatus()) {
                return 100.0;
            } else {
                return 0.0;
            }
        }
        if (!$latestFeedback) {
            return 0;
        }
        return $participant->getNormalisedMark($publishedOnly);
    }
}
