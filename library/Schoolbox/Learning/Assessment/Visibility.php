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
     *  - Instructor                  - can see anytime (*)
     *  - Learner                     - can only see if a participant, and after publish date, and not excluded by work type (*)
     *  - Non-instructor, non-learner - if staff, can see anytime (*)
     *                                  if parent of learner, can see if child learner can see
     *                                  otherwise can only see if a participant, and after publish date, and not excluded by work type (*)
     *
     * (*) For all user types do not show if closed assessments are hidden and close date has passed.
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
        $participant = $assessable->getParticipantIfExtant($learner);
        $closeDate   = $participant ? $participant->getCloseDate() : $assessable->getCloseAt();

        return (
            ($workType === null || !$respectWorkTypeVisibility || $workType->isVisibleToLearners()) &&
            $participant !== null &&
            $assessable->isPublished($learner, $now) &&
            ($showClosed || $now->lt($closeDate))
        );
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

            $childParticipants = new GenericCollection(Participant::class);

            foreach ($childLearners as $childLearner) {
                $participant = $assessable->getParticipantIfExtant($childLearner);

                if ($participant === null) {
                    continue;
                }

                $childParticipants->add($participant);
            }

            // Return true if the parent has any child participant for whom the assessment is visible.
            foreach ($childParticipants as $childParticipant) {
                $publishDate = $childParticipant->getPublishDate();

                if (null === $publishDate || $now->lt($publishDate)) {
                    continue;
                }

                if ($showClosed) {
                    // The assessment is published for this child participant, and $showClosed is true.
                    // Therefore, there is no need to check the close date.
                    return true;
                }

                $closeDate = $childParticipant->getCloseDate();

                if (null === $closeDate || $now->lt($closeDate)) {
                    // The assessment is published and either will never close or has not yet closed.
                    // Therefore, the assessment is visible to this child participant and hence the parent.
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
