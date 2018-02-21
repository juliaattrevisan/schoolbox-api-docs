<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Quiz;

use Schoolbox\Permission\Access\Access;
use Alaress_Date_Format;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\Quiz;

/**
 * Defines the business rules for what actions are available for a quiz or quiz attempt, respecting the new
 * Custom Assessment Participation (CAP) business rules.
 *
 * Implements the route perm responsibilities for the old quiz sub-system that are typically
 * handled by route auth rules in the new Aura system.
 */
class ActionValidator
{

    /** @var \Alaress_Date_Format */
    private $now;
    /** @var Quiz\Model */
    private $quiz;
    /** @var Access */
    private $accessHelper;


    /**
     * @param Alaress_Date_Format $now
     * @param Quiz\Model          $quiz
     * @param Access              $accessHelper
     */
    public function __construct(Alaress_Date_Format $now, Quiz\Model $quiz, Access $accessHelper)
    {
        $this->now          = $now;
        $this->quiz         = $quiz;
        $this->accessHelper = $accessHelper;
    }


    /**
     * Returns whether the specified user can see that this quiz exists.
     *
     * @param User $user
     * @return bool
     */
    public function canSeeQuiz(User $user): bool
    {
        $isInstructor = $this->accessHelper->isInstructor($user, $this->quiz->getContext()->getFolder());
        $isStaff      = $user->getRole()->isStaff();

        // Instructors/Staff can always view the assessment (regardless of whether they can interact with it)
        if ($isInstructor || $isStaff) {
            return true;
        }

        // Does user have a participation record?
        $participant = $this->quiz->getParticipantIfExtant($user);
        if ($participant) {
            // Excluded Learners can never see the quiz
            if ($participant->isExcluded()) {
                return false;
            }
            // Non-excluded Learners can only see assessment after publishing
            return $this->now->gte($participant->getPublishDate());
        }

        // Not an instructor/staff, and no participation record.
        return false;
    }


    /**
     * Returns whether the specified user is permitted to attempt the quiz.
     *
     * @param User $user
     * @return bool
     */
    public function canAttemptQuiz(User $user): bool
    {
        $isInstructor = $this->accessHelper->isInstructor($user, $this->quiz->getContext()->getFolder());
        $participant  = $this->quiz->getParticipantIfExtant($user);

        // Does user have a participation for this assessment?
        if ($participant) {
            // Users who have been explicitly excluded can never attempt
            if ($participant->isExcluded()) {
                return false;
            }
            // Instructors are not bound by dates, Learners are.
            return $isInstructor || ($this->now->gte($participant->getOpenDate()) && $this->now->lt($participant->getCloseDate()));
        }

        // Users with no participation can attempt they are instructors, and a dynamic plan is available
        return $isInstructor && $this->quiz->getDynamicPlan();
    }


    /**
     * Returns whether a user can review another users' attempts for this quiz
     *
     * @param User $reviewer
     * @param User $reviewee
     * @return bool
     */
    public function canReviewAttempts(User $reviewer, User $reviewee): bool
    {
        $isInstructor  = $this->accessHelper->isInstructor($reviewer, $this->quiz->getContext()->getFolder());
        $isParticipant = $this->quiz->isParticipant($reviewee);

        // Only instructors can review, and reviewee cannot be excluded.
        return $isInstructor && $isParticipant;
    }


    /**
     * Returns whether the specified user is permitted to delete attempts for this quiz
     *
     * @param User $deleter
     * @param User $participantUser
     * @return bool
     */
    public function canDeleteAttempts(User $deleter, User $participantUser): bool
    {
        $isInstructor  = $this->accessHelper->isInstructor($deleter, $this->quiz->getContext()->getFolder());
        $isParticipant = $this->quiz->isParticipant($participantUser);

        // Only instructors can delete, and participant cannot be excluded.
        return $isInstructor && $isParticipant;
    }
}
