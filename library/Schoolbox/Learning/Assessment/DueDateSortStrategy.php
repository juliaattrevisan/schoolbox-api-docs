<?php

declare(strict_types=1);

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress_Date_Format;
use Schoolbox\User;

/**
 * Strategy for sorting assessments.
 *
 * This orders them by their due date (either base or participant's),
 * then by their sequence, then by their title.
 *
 * Usage: `$assessmentCollection->sortByCallback(new DueDateSortStrategy($student));`
 */
class DueDateSortStrategy
{
    /**
     * The user who is participating in the assessments that are being sorted.
     * This user's customised due date is used in preference to the assessment
     * due date in comparisons.
     *
     * @var User
     */
    private $user;

    /**
     * Constructor
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Callback for sorting assessments.
     *
     * @param IAssessable $a
     * @param IAssessable $b
     * @return int
     */
    public function __invoke(IAssessable $a, IAssessable $b)
    {
        // Order by participant's due date, not assessment's
        $aDate = $this->retrieveDateForSort($a);
        $bDate = $this->retrieveDateForSort($b);
        if (
            $aDate instanceof Alaress_Date_Format
            && $bDate instanceof Alaress_Date_Format
            && !$aDate->isNull()
            && !$bDate->isNull()
        ) {
            $dateComp = $aDate->compare($bDate);
            if ($dateComp) {
                return $dateComp;
            }
        }

        // Sequence
        $sequenceComp = $a->getSequence() <=> $b->getSequence();
        if ($sequenceComp) {
            return $sequenceComp;
        }

        // Title
        return strnatcasecmp($a->getTitle(), $b->getTitle());
    }

    /**
     * Retrieves date for sorting out of an assessment.
     *
     * @param IAssessable $assessment
     * @return Alaress_Date_Format|null
     */
    private function retrieveDateForSort(IAssessable $assessment): ?Alaress_Date_Format
    {
        if ($assessment->isParticipant($this->user)) {
            $participant = $assessment->getParticipantIfExtant($this->user);
            if ($participant instanceof Participant) {
                return $participant->getDueDate();
            }
        }
        return $assessment->getDueAt();
    }
}
