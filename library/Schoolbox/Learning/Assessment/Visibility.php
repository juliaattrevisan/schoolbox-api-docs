<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Permission\Access\Access;
use Alaress_Date_Format;

class Visibility
{
    /**
     * @var Access
     */
    private Access $access;

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
     * The below rules summarise the authoritative descriptions in WorkType\Mapper.
     *  - Instructor          - can see anytime (*)
     *  - Learner-participant - can only see if after publish date, and not excluded by work type (*)
     *  - Everyone else       - if staff, can see anytime (*)
     *                          if parent of learner-participant, can see if child learner can see
     *                          otherwise can only see if a participant, and after publish date,
     *                          and not excluded by work type (*)
     *
     * (*) For all user types the assessment is not visible if closed assessments are hidden and the close date has passed.
     *
     * @see WorkType\Mapper for glossary of terms used above.
     *
     * @param IAssessable              $assessment Must have plans and participants loaded if viewer is a participant
     * @param User                     $viewer
     * @param bool                     $showClosed Should we exclude assessments if their close date has passed?
     * @param Alaress_Date_Format|null $now
     * @param bool                     $respectWorkTypeVisibility
     * @return bool
     */
    public function isVisibleToUser(
        IAssessable $assessable,
        User $viewer,
        bool $showClosed,
        ?Alaress_Date_Format $now = null,
        bool $respectWorkTypeVisibility = true
    ): bool {
        $now = $now ?? Alaress_Date_Format::now();
        $folder = $assessable->getBaseContext()->getFolder();

        // Handle case where user is instructor.
        try {
            $viewerIsInstructor = $this->access->isInstructor($viewer, $folder);
        } catch (MapperException | \LogicException) {
            $viewerIsInstructor = false; // In case of exception, assume non-instructor.
        }
        if ($viewerIsInstructor) {
            return $this->isVisibleToInstructor($assessable, $viewer, $showClosed, $now);
        }

        // Handle case where user is a learner-participant.
        try {
            $viewerIsLearner = $this->access->isLearner($viewer, $folder);
        } catch (MapperException | \LogicException) {
            $viewerIsLearner = false; // In case of exception, assume non-learner.
        }
        $viewerParticipant = $assessable->getParticipantIfExtant($viewer);
        if ($viewerIsLearner && $viewerParticipant !== null) {
            return $this->isVisibleToLearnerParticipant(
                $assessable,
                $viewer,
                $viewerParticipant,
                $showClosed,
                $now,
                $respectWorkTypeVisibility
            );
        }

        // Handle all other cases (including where user is parent of learner-participant).
        return $this->isVisibleToNonInstructorNonLearnerParticipant($assessable, $viewer, $showClosed, $now, $respectWorkTypeVisibility);
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
     * Is the assessable visible to the learner-participant? (Assumes the user is a learner-participant)
     *
     * @param IAssessable         $assessable
     * @param User                $learnerParticipant The viewer who is a learner and a participant in $assessable.
     * @param Participant         $participant The participant object for $learnerParticipant.
     * @param bool                $showClosed Should we show assessments after their closed date?
     * @param Alaress_Date_Format $now
     * @param bool                $respectWorkTypeVisibility
     * @return bool
     */
    private function isVisibleToLearnerParticipant(
        IAssessable $assessable,
        User $learnerParticipant,
        Participant $participant,
        bool $showClosed,
        Alaress_Date_Format $now,
        bool $respectWorkTypeVisibility
    ): bool {
        $workType = $assessable->getWorkType();
        $closeDate = $participant->getCloseDate();

        if ($learnerParticipant->getId() !== $participant->getUser()->getId()) {
            throw new \UnexpectedValueException('');
        }

        // Apply special lesson plan visibility logic.
        if ($this->hideAssessmentDueToLessonPlanVisibility($assessable, $learnerParticipant)) {
            return false;
        }

        return (
            ($workType === null || !$respectWorkTypeVisibility || $workType->isVisibleToLearners()) &&
            $assessable->isPublished($learnerParticipant, $now) &&
            ($showClosed || $now->lt($closeDate))
        );
    }

    /**
     * Is the assessable visible to a user who is not an instructor and not a learner-participant?
     *
     * @param IAssessable         $assessable
     * @param User                $nonInstructorNonLearnerParticipant
     * @param bool                $showClosed Should we show assessments after their closed date?
     * @param Alaress_Date_Format $now
     * @param bool                $respectWorkTypeVisibility
     * @return bool
     */
    private function isVisibleToNonInstructorNonLearnerParticipant(
        IAssessable $assessable,
        User $nonInstructorNonLearnerParticipant,
        bool $showClosed,
        Alaress_Date_Format $now,
        bool $respectWorkTypeVisibility
    ): bool {
        /** Handle case where the user is a staff member. */
        // Note we do not have to consider the case of a staff member who is also a parent of a learner.  That is
        // because although it is possible to define such a user in the sis_... tables, Schoolbox's user provisioning
        // process will create two users in that situation. One with role type 'staff', the other with role type
        // 'parent'.
        if ($nonInstructorNonLearnerParticipant->getRole()->isStaff()) {
            if ($showClosed) {
                return true;
            }
            // If staff member is also a participant, respect their custom close date.
            $participant = $assessable->getParticipantIfExtant($nonInstructorNonLearnerParticipant);
            $closeDate   = $participant ? $participant->getCloseDate() : $assessable->getCloseAt();
            return $now->lt($closeDate);
        }

        $workType = $assessable->getWorkType();
        $participant = $assessable->getParticipantIfExtant($nonInstructorNonLearnerParticipant);

        /** Handle case where work type visibility should be ignored or cannot be determined. */
        if (!$respectWorkTypeVisibility || $workType === null) {
            if (!$participant) {
                return false;
            }

            // Since the user who is not a learner-participant may still have
            // role type 'student', apply special lesson plan visibility logic.
            if ($this->hideAssessmentDueToLessonPlanVisibility($assessable, $nonInstructorNonLearnerParticipant)) {
                return false;
            }

            $closeDate   = $participant->getCloseDate();
            $isPublished = $now->gte($participant->getPublishDate());
            return $isPublished && ($showClosed || $now->lt($closeDate));
        }

        /** Handle case where the user is a parent of one or more learner-participants. */
        try {
            $childLearners = $this->access->getChildLearnersOfParent(
                $nonInstructorNonLearnerParticipant,
                $assessable->getBaseContext()->getFolder()
            );
        } catch (MapperException | \LogicException | \UnexpectedValueException) {
            $childLearners = new GenericCollection(User::class); // In case of exception, assume no child learner exists.
        }
        if ($childLearners->count() > 0 && $workType->isVisibleToParents()) {
            $childParticipants = new GenericCollection(Participant::class);
            foreach ($childLearners as $childLearner) {
                // Apply special lesson plan visibility logic.
                // (If lesson plan is hidden from students, it should be hidden from parents also).
                if ($this->hideAssessmentDueToLessonPlanVisibility($assessable, $nonInstructorNonLearnerParticipant)) {
                    continue; // Skip student due to special lesson plan visibility logic.
                }

                $childParticipant = $assessable->getParticipantIfExtant($childLearner);
                if ($childParticipant === null) {
                    continue;
                }
                $childParticipants->add($childParticipant);
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

        // If execution reaches this point, the user is not:
        //  * an instructor
        //  * a learner-participant
        //  * a staff member
        //  * a parent of a learner-participant
        return false;
    }

    /**
     * Lesson Plans have some specific logic for visibility:
     * - Lesson Plans can be hidden from students via a Visibility switch
     * If you're a student, hide any lesson plans that were hidden from you
     * - Lesson Plans that are not hidden from students should be visible to all students
     */
    private function hideAssessmentDueToLessonPlanVisibility(IAssessable $assessable, User $viewer): bool
    {
        return (
            $assessable instanceof LessonPlan\Model &&
            Role::STUDENT !== $assessable->getVisibility() &&
            $viewer->getRole()->isStudent()
        );
    }
}
