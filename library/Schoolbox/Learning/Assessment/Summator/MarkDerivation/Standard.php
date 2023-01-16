<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Summator\MarkDerivation;

use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Task;

/**
 * Standard strategy for deriving the normalised mark for a participant:
 * simply retrieve it directly from the participant record.
 */
class Standard implements IStrategy
{
    /**
     * @inheritdoc
     */
    public function deriveNormalisedMark(Participant $participant, bool $publishedOnly): ?float
    {
        // Like the `ZeroBeforeFeedback` strategy:
        // if the assessment for which a mark is being derived is a task,
        // calculate the normalised mark here.
        // In this strategy though, the normalised mark is null instead of zero
        // if the task is not marked as completed by the teacher.
        if ($participant->getAssessable() instanceof Task\Model) {
            $latestResponse = $participant->findMostRecentResponse();
            if (
                $latestResponse instanceof Task\Response\Model
                && Completion::STATUS_TEACHER_ASSESSED_COMPLETE === $latestResponse->getCompletionStatus()
            ) {
                return 100.0;
            } else {
                return null;
            }
        }
        return $participant->getNormalisedMark($publishedOnly);
    }
}
