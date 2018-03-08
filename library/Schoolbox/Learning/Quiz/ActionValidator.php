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
    private $accessHelper;


    /**
     * @param Alaress_Date_Format $now
     * @param Access              $accessHelper
     */
    public function __construct(Alaress_Date_Format $now, Access $accessHelper)
    {
        $this->now          = $now;
        $this->accessHelper = $accessHelper;
    }


    /**
     * Returns whether the specified user can see that this quiz exists.
     *
     * @param User       $user
     * @param Quiz\Model $quiz
     * @param bool       $restrictStudentSubmissions Are parents restricted from viewing student submissions?
     * @return bool
     */
    public function canSeeQuiz(User $user, Quiz\Model $quiz, bool $restrictStudentSubmissions): bool
    {
        $folder       = $quiz->getBaseContext()->getFolder();
        $isInstructor = $this->accessHelper->isInstructor($user, $folder);
        $isStaff      = $user->getRole()->isStaff();

        // Instructors/Staff can always view the assessment (regardless of whether they can interact with it)
        if ($isInstructor || $isStaff) {
            return true;
        }

        // Does user have a participation record?
        $participant = $quiz->getParticipantIfExtant($user);
        if ($participant) {
            // Excluded Learners can never see the quiz
            if ($participant->isExcluded()) {
                return false;
            }
            // Non-excluded Learners can only see assessment after publishing
            return $this->now->gte($participant->getPublishDate());
        }

        // Am I a parent?
        // I can see the quiz if:
        // * I have access to its folder
        // * I'm not blocked by config.restrict_student_submissions
        // * one of my kids is a participant
        if (!$restrictStudentSubmissions
            && $this->accessHelper->getFolderAccess($user, $folder->getId()) >= UserAccess::ACCESS_READ
        ) {
            foreach ($quiz->getAllParticipants() as $participant) {
                if ($participant->isExcluded() || $this->now->lt($participant->getPublishDate())) {
                    continue;
                }
                if ($this->now->gte($participant->getPublishDate()) && $user->isParentOf($participant->getUser())) {
                    return true;
                }
            }
        }

        // Not an instructor/staff, and no participation record.
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
        $isInstructor = $this->accessHelper->isInstructor($user, $quiz->getContext()->getFolder());
        $participant  = $quiz->getParticipantIfExtant($user);

        // Does user have a participation for this assessment?
        if ($participant) {
            // Users who have been explicitly excluded can never attempt
            if ($participant->isExcluded()) {
                return false;
            }

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
        $isInstructor  = $this->accessHelper->isInstructor($reviewer, $quiz->getContext()->getFolder());
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
        if (!$participant || $participant->isExcluded()) {
            return false;
        }

        // Instructors can see attempts, as long as student is participating
        if ($this->accessHelper->isInstructor($user, $folder)) {
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
            && $user->isParentOf($student)
            && $this->accessHelper->getFolderAccess($user, $folder->getId()) >= UserAccess::ACCESS_READ;
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
    ): bool
    {
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
        $isInstructor  = $this->accessHelper->isInstructor($deleter, $quiz->getContext()->getFolder());
        $isParticipant = $quiz->isParticipant($participantUser);

        // Only instructors can delete, and participant cannot be excluded.
        return $isInstructor && $isParticipant;
    }
}
