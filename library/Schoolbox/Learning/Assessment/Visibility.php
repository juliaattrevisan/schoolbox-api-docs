<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

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
     * Does not consider whether a parent can see the assessment because one of their children can. This is to ensure the caller
     * can distinguish the case where a parent can view the assessment themselves, vs seeing it due to their child's visibility.
     *
     *  - Instructor               - can see anytime (so long as not past close and we are hiding closed)
     *  - Learner                  - excluded if the work type hides learners; otherwise same as staff/participant rules
     *  - Staff (non-learner)      - can see anytime (so long as not past close and we are hiding closed)
     *  - NonStaff + NonInstructor - can only see if a participant, and after publish date, and not pure SIVO
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
        } catch (MapperException) {
            $viewerIsInstructor = false; // In case of mapper error, assume non-instructor.
        }

        if ($viewerIsInstructor) {
            return $this->isVisibleToInstructor($assessment, $viewer, $showClosed, $now);
        }

        try {
            $viewerIsLearner = $this->access->isLearner($viewer, $folder);
        } catch (MapperException) {
            $viewerIsLearner = false; // In case of mapper error, assume non-learner.
        }

        if ($viewerIsLearner) {
            return $this->isVisibleToLearner($assessment, $viewer, $showClosed, $now, $respectWorkTypeVisibility);
        }

        return $this->isVisibleToNonInstructorNonLearner($assessment, $viewer, $showClosed, $now, $respectWorkTypeVisibility);
    }


    /**
     * Returns whether a user is permitted to see the existence of an assessment, or one of the user's children can.
     *
     * Use this when you don't need to know whether the user can see the assessment due to their own permissions, or
     * because of one of their children
     *
     * @param IAssessable              $assessment
     * @param User                     $viewer
     * @param bool                     $showClosed
     * @param Alaress_Date_Format|null $now
     * @param bool                     $respectWorkTypeVisibility
     * @return bool
     */
    public function isVisibleToUserOrChildOfUser(
        IAssessable $assessment,
        User $viewer,
        bool $showClosed,
        ?Alaress_Date_Format $now = null,
        bool $respectWorkTypeVisibility = true
    ): bool {
        $now = $now ?? Alaress_Date_Format::now();

        if ($this->isVisibleToUser($assessment, $viewer, $showClosed, $now, $respectWorkTypeVisibility)) {
            return true;
        }
        if ($viewer->getRole()->isParent()) {
            foreach ($viewer->getChildren() as $child) {
                if ($this->isVisibleToUser($assessment, $child, $showClosed, $now, $respectWorkTypeVisibility)) {
                    return true;
                }
            }
        }
        return false;
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
        $workType = $assessable->getWorkType();

        try {
            $childLearner = $this->access->getChildLearnerOfParent(
                $nonInstructorNonLearner,
                $assessable->getBaseContext()->getFolder()
            );
        } catch (MapperException) {
            $childLearner = null; // In case of mapper error, assume no child learner exists.
        }

        // Handle the case where the user is a parent of a learner.
        if (
            $respectWorkTypeVisibility && $workType !== null
            && $childLearner !== null && $workType->isVisibleToParents()
        ) {
            // If learner (child of parent of learner) is also a participant, respect their custom close date.
            // Also, parents should only see published assessments - similar to learners and non-learner participants.
            $participant = $assessable->getParticipantIfExtant($childLearner);
            $closeDate   = $participant ? $participant->getCloseDate() : $assessable->getCloseAt();
            $isPublished = $now->gte($participant->getPublishDate());
            return $isPublished && ($showClosed || $now->lt($closeDate));
        }

        // Handle the case where the user is a staff member.
        if ($nonInstructorNonLearner->getRole()->isStaff()) {
            if ($showClosed) {
                return true;
            }
            // If staff member is also a participant, respect their custom close date.
            $participant = $assessable->getParticipantIfExtant($nonInstructorNonLearner);
            $closeDate   = $participant ? $participant->getCloseDate() : $assessable->getCloseAt();
            return $now->lt($closeDate);
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
