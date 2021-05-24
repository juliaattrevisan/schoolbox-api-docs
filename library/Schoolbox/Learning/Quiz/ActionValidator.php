<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Quiz;

use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Permission\Access\Access;
use Alaress_Date_Format;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\Quiz;

/**
 * Defines the business rules for what actions are available for a quiz or quiz attempt, respecting the new
 * Custom Assessment Participation (CAP) business rules.
 *
 * Implements the route perm responsibilities for the old quiz sub-system that are typically
 * handled by route auth rules in the new Aura system.
 *
 * Typically, you'd use any given test in two places:
 * 1. To determine if a link should be shown, and
 * 2. The same test should be repeated on the linked page and a 404 displayed if the test fails
 */
class ActionValidator
{

    /** @var \Alaress_Date_Format */
    private $now;

    /** @var Access */
    private $access;

    /** @var Visibility */
    private $visibility;


    /**
     * @param Alaress_Date_Format $now
     * @param Access              $access
     */
    public function __construct(Alaress_Date_Format $now, Access $access)
    {
        $this->now        = $now;
        $this->access     = $access;
        $this->visibility = new Visibility($access);
    }


    /**
     * Returns whether the specified user can see that this quiz exists.
     *
     * @param User       $user
     * @param Quiz\Model $quiz
     * @return bool
     */
    public function canSeeQuiz(User $user, Quiz\Model $quiz): bool
    {
        if ($this->visibility->isVisibleToUser($quiz, $user, true, $this->now)) {
            return true;
        }
        if ($user->getRole()->isParent()) {
            foreach ($user->getChildren() as $child) {
                if ($this->visibility->isVisibleToUser($quiz, $child, true, $this->now)) {
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * Returns whether the specified user is permitted to attempt the quiz.
     *
     * @param User       $user
     * @param Quiz\Model $quiz
     * @return bool
     */
    public function canAttemptQuiz(User $user, Quiz\Model $quiz): bool
    {
        if (!$this->canSeeQuiz($user, $quiz)) {
            return false;
        }

        $isInstructor = $this->access->isInstructor($user, $quiz->getContext()->getFolder());
        $participant  = $quiz->getParticipantIfExtant($user);

        // Does user have a participation for this assessment?
        if ($participant) {
            // Instructors are not bound by the normal rules
            if ($isInstructor) {
                return true;
            }

            // let the quiz decide the user's fate
            return $quiz->canLearnerCreateResponse($user);
        }

        // Users with no participation can attempt they are instructors, and a dynamic plan is available
        return $isInstructor && $quiz->getDynamicPlan();
    }


    /**
     * Returns whether a user can review another users' attempts for this quiz
     *
     * @param User       $reviewer
     * @param User       $reviewee
     * @param Quiz\Model $quiz
     * @return bool
     */
    public function canReviewAttempts(User $reviewer, User $reviewee, Quiz\Model $quiz): bool
    {
        $isInstructor  = $this->access->isInstructor($reviewer, $quiz->getContext()->getFolder());
        $isParticipant = $quiz->isParticipant($reviewee);

        // Only instructors can review, and reviewee cannot be excluded.
        return $isInstructor && $isParticipant;
    }


    /**
     * Can `$user` see attempts made by `$student` on `$quiz`?
     *
     * This covers only whether `$user` can see that attempts exist; it doesn't
     * cover whether they can view the attempt itself.
     * (@see `canReviewAttemptsReadonly` for that)
     *
     * @param User       $user
     * @param User       $student
     * @param Quiz\Model $quiz
     * @param bool       $restrictStudentSubmissions Are parents restricted from viewing student submissions?
     * @return bool
     */
    public function canSeeAttempts(User $user, User $student, Quiz\Model $quiz, bool $restrictStudentSubmissions): bool
    {
        // Is student even participating?
        $folder      = $quiz->getBaseContext()->getFolder();
        $participant = $quiz->getParticipantIfExtant($student);
        if (!$participant) {
            return false;
        }

        // Ensure user is permitted to see the quiz
        if (!$this->canSeeQuiz($user, $quiz)) {
            return false;
        }

        // Instructors can see attempts, as long as student is participating
        if ($this->access->isInstructor($user, $folder)) {
            return true;
        }

        // Anyone else:
        // Can't see participation if it's not published yet
        if ($this->now->lt($participant->getPublishDate())) {
            return false;
        }

        // Student may view their own attempts
        if ($user->getId() === $student->getId()) {
            return true;
        }

        // Parent of student may view participation
        // (as long as config.restrict_student_submissions is off)
        return !$restrictStudentSubmissions
            && $user->isParentOf($student);
    }


    /**
     * Can `$user` read-only review an attempt made by `$student` on `$quiz`?
     *
     * @param User       $user
     * @param User       $student
     * @param Quiz\Model $quiz
     * @param bool       $restrictStudentSubmissions Are parents restricted from viewing student submissions?
     * @return bool
     */
    public function canReviewAttemptsReadonly(
        User $user,
        User $student,
        Quiz\Model $quiz,
        bool $restrictStudentSubmissions
    ): bool {
        return
            $quiz->isFeedbackVisibleToUser($user)
            && $this->canSeeAttempts($user, $student, $quiz, $restrictStudentSubmissions);
    }


    /**
     * Returns whether the specified user is permitted to delete attempts for this quiz
     *
     * @param User       $deleter
     * @param User       $participantUser
     * @param Quiz\Model $quiz
     * @return bool
     */
    public function canDeleteAttempts(User $deleter, User $participantUser, Quiz\Model $quiz): bool
    {
        $isInstructor  = $this->access->isInstructor($deleter, $quiz->getContext()->getFolder());
        $isParticipant = $quiz->isParticipant($participantUser);

        // Only instructors can delete, and participant cannot be excluded.
        return $isInstructor && $isParticipant;
    }
}
