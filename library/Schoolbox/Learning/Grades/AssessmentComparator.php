<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;
use Schoolbox\Learning\Markbook\SortForMarkbook;
use UnexpectedValueException;

/**
 * Respects CAP dates for a specific user
 */
class AssessmentComparator extends SortForMarkbook
{

    /** @var User */
    private $student;


    /**
     * @param User $student
     */
    public function __construct(User $student)
    {
        $this->student = $student;
    }


    /**
     * Compares assessments by Participant's Due Date
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     * @throws UnexpectedValueException
     */
    protected function compareDueDates(IAssessable $left, IAssessable $right): int
    {
        $leftParticipant  = $left->getParticipant($this->student);
        $rightParticipant = $right->getParticipant($this->student);

        $leftDate  = $leftParticipant ? $leftParticipant->getDueDate() : $left->getDueAt();
        $rightDate = $rightParticipant ? $rightParticipant->getDueDate() : $right->getDueAt();

        return $leftDate->compare($rightDate);
    }
}
