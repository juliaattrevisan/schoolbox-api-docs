<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\Permission\Access\Access;
use Alaress_Date_Format;

class Visibility
{
    /**
     * @var Access
     */
    private $access;


    /**
     * @param Access $access
     */
    public function __construct(Access $access)
    {
        $this->access = $access;
    }


    /**
     * Determines if the specified user can see an IAssessable (but not necessarily its details or participant interactions)
     *
     * The below rules summarise the authoritative descriptions in Assessment\WorkType\Mapper.
     *  - Instructor               - can see anytime (so long as not past close and we are hiding closed)
     *  - Learner                  - excluded if the work type hides learners; otherwise same as staff/participant rules
     *  - Staff (non-learner)      - can see anytime (so long as not past close and we are hiding closed)
     *  - NonStaff + NonInstructor - can only see if a participant, and after publish date, and not pure staff/instructor only
     *                               (note parents of learners are handled in this category of user)
     *
     * Staff      - is any user with role-type of 'staff'
     * Instructor - is any user with WRITE+ perm to folder
     * Learner    - is any user with READ perm to folder (overrides staff when work type hides learners)
     *
     * @param IAssessable              $assessment Must have plans and participants loaded if viewer is a participant
     * @param User                     $viewer
     * @param bool                     $showClosed Should we exclude assessments if their close date has passed?
     * @param Alaress_Date_Format|null $now
     * @param bool                     $respectWorkTypeVisibility
     * @return bool
     */
    public function isVisibleToUser(
        IAssessable $assessment,
        User $viewer,
        bool $showClosed,
        ?Alaress_Date_Format $now = null,
        bool $respectWorkTypeVisibility = true
    ): bool {
        $now = $now ?? Alaress_Date_Format::now();
        $folder = $assessment->getBaseContext()->getFolder();

        try {
            $viewerIsInstructor = $this->access->isInstructor($viewer, $folder);
        } catch (MapperException | \LogicException) {
            $viewerIsInstructor = false; // In case of exception, assume non-instructor.
        }

        if ($viewerIsInstructor) {
            return $this->isVisibleToInstructor($assessment, $viewer, $showClosed, $now);
        }

        try {
            $viewerIsLearner = $this->access->isLearner($viewer, $folder);
        } catch (MapperException | \LogicException) {
            $viewerIsLearner = false; // In case of exception, assume non-learner.
        }

        if ($viewerIsLearner) {
            return $this->isVisibleToLearner($assessment, $viewer, $showClosed, $now, $respectWorkTypeVisibility);
        }

        return $this->isVisibleToNonInstructorNonLearner($assessment, $viewer, $showClosed, $now, $respectWorkTypeVisibility);
    }


    /**
     * Is the assessable visible to the Instructor? (Assumes the user is an instructor)
     *
     * @param IAssessable         $assessable
     * @param User                $instructor
     * @param bool                $showClosed Should we show assessments after their closed date?
     * @param Alaress_Date_Format $now
     * @return bool
     */
    private function isVisibleToInstructor(
        IAssessable $assessable,
        User $instructor,
        bool $showClosed,
        Alaress_Date_Format $now
    ): bool {
        if ($showClosed) {
            return true;
        }
        // If instructor is also a participant, respect their custom close date.
        $participant = $assessable->getParticipantIfExtant($instructor);
        $closeDate   = $participant ? $participant->getCloseDate() : $assessable->getCloseAt();
        return $now->lt($closeDate);
    }


    /**
     * Is the assessable visible to the Learner? (Assumes the user is a learner)
     *
     * @param IAssessable         $assessable
     * @param User                $learner
     * @param bool                $showClosed Should we show assessments after their closed date?
     * @param Alaress_Date_Format $now
     * @param bool                $respectWorkTypeVisibility
     * @return bool
     */
    private function isVisibleToLearner(
        IAssessable $assessable,
        User $learner,
        bool $showClosed,
        Alaress_Date_Format $now,
        bool $respectWorkTypeVisibility
    ): bool {
        $workType = $assessable->getWorkType();
        if ($respectWorkTypeVisibility && $workType !== null && !$workType->isVisibleToLearners()) {
            return false;
        }
        // Aside from the check above, visibility for learners is the same as for non-learners, so defer to below function.
        return $this->isVisibleToNonInstructorNonLearner($assessable, $learner, $showClosed, $now, $respectWorkTypeVisibility);
    }


    /**
     * Is the assessable visible to a non-instructor non-learner? (Assumes the user is neither instructor nor learner)
     *
     * @param IAssessable         $assessable
     * @param User                $nonInstructorNonLearner
     * @param bool                $showClosed Should we show assessments after their closed date?
     * @param Alaress_Date_Format $now
     * @param bool                $respectWorkTypeVisibility
     * @return bool
     */
    private function isVisibleToNonInstructorNonLearner(
        IAssessable $assessable,
        User $nonInstructorNonLearner,
        bool $showClosed,
        Alaress_Date_Format $now,
        bool $respectWorkTypeVisibility
    ): bool {
        // Handle the case where the user is a staff member.
        // Note we do not have to consider the case of a staff member who is also a parent of a learner.  That is
        // because although it is possible to define such a user in the sis_... tables, Schoolbox's user provisioning
        // process will create two users in that situation. One with role type 'staff', the other with role type
        // 'parent'.
        if ($nonInstructorNonLearner->getRole()->isStaff()) {
            if ($showClosed) {
                return true;
            }
            // If staff member is also a participant, respect their custom close date.
            $participant = $assessable->getParticipantIfExtant($nonInstructorNonLearner);
            $closeDate   = $participant ? $participant->getCloseDate() : $assessable->getCloseAt();
            return $now->lt($closeDate);
        }

        $workType = $assessable->getWorkType();

        try {
            $childLearners = $this->access->getChildLearnersOfParent(
                $nonInstructorNonLearner,
                $assessable->getBaseContext()->getFolder()
            );
        } catch (MapperException | \LogicException | \UnexpectedValueException) {
            $childLearners = new GenericCollection(User::class); // In case of exception, assume no child learner exists.
        }

        // Handle the case where the user is a parent of one or more learners.
        if (
            $respectWorkTypeVisibility && $workType !== null
            && $childLearners->count() > 0 && $workType->isVisibleToParents()
        ) {
            // Apply special lesson plan visibility logic.
            // (If lesson plan is hidden from students, it should be hidden from parents also).
            if ($assessable instanceof LessonPlan\Model && \Schoolbox\Role::STUDENT !== $assessable->getVisibility()) {
                return false;
            }

            // Return true if the assessment is visible to any child learner.
            foreach ($childLearners as $childLearner) {
                if (!$assessable->isPublished($childLearner, $now)) {
                    // The assessment is not published for this child learner, so is not visible.
                    // Move on to the next child learner.
                    continue;
                }

                if ($showClosed) {
                    // The assessment is published for this child learner, and $showClosed is true.
                    // Therefore, there is no need to check the close date.
                    return true;
                }

                $participant = $assessable->getParticipantIfExtant($childLearner);
                $closeDate = $participant ? $participant->getCloseDate() : $assessable->getCloseAt();

                if (null === $closeDate || $now->lt($closeDate)) {
                    // The assessment is published and either will never close or has not yet closed.
                    // Therefore, the assessment is visible to this child learner and hence the parent.
                    return true;
                }
            }

            return false;
        }

        if (
            $respectWorkTypeVisibility && $workType !== null
            && !$workType->isVisibleToLearners() && !$workType->isVisibleToParents()
        ) {
            // This work type is visible only to staff and instructors.  Since the user is a non-instructor and not
            // staff (since the staff case was handled above), the assessment should not be visible to the user.
            return false;
        }

        $participant = $assessable->getParticipantIfExtant($nonInstructorNonLearner);
        if (!$participant) {
            return false;
        }

        /**
         * Lesson Plans have some specific logic for visibility:
         * - Lesson Plans can be hidden from students via a Visibility switch
         * If you're a student, hide any lesson plans that were hidden from you
         * - Lesson Plans that are not hidden from students, should be visible to all students
         */
        if ($assessable instanceof LessonPlan\Model && \Schoolbox\Role::STUDENT !== $assessable->getVisibility()) {
            return false;
        }

        $closeDate   = $participant->getCloseDate();
        $isPublished = $now->gte($participant->getPublishDate());
        return $isPublished && ($showClosed || $now->lt($closeDate));
    }
}
