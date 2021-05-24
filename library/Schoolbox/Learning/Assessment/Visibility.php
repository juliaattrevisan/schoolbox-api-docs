<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\User;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
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
     * * Staff                    - can see anytime (so long as not past close and we are hiding closed)
     * * Instructor               - can see anytime (so long as not past close and we are hiding closed)
     * * NonStaff + NonInstructor - can only see if a participant, and after publish date, and not SIVO
     *
     * Staff      - is considered any user with role-type of 'staff'
     * Instructor - is considered any user with WRITE+ perm to folder
     *
     * SIVO => 'Staff/Instructor visible only' - derived from the assessment's workType
     *
     * @param IAssessable              $assessment Must have plans and participants loaded if viewer is a participant
     * @param User                     $viewer
     * @param bool                     $showClosed Should we exclude assessments if their close date has passed?
     * @param Alaress_Date_Format|null $now
     * @param bool                     $respectSivo
     * @return bool
     */
    public function isVisibleToUser(IAssessable $assessment, User $viewer, bool $showClosed, Alaress_Date_Format $now = null, bool $respectSivo = true): bool
    {
        $now = $now ?? Alaress_Date_Format::now();

        /** @noinspection NotOptimalIfConditionsInspection */
        if ($viewer->getRole()->isStaff() && $this->isVisibleToStaff($assessment, $viewer, $showClosed, $now)) {
            return true;
        }

        // Is viewer an instructor?
        if ($this->access->getFolderAccess($viewer, $assessment->getBaseContext()->getFolder()->getId()) >= UserAccess::ACCESS_WRITE) {
            if ($this->isVisibleToInstructor($assessment, $viewer, $showClosed, $now)) {
                return true;
            }
        } // Not an instructor. Can they see due to their participation?
        elseif ($this->isVisibleToNonInstructor($assessment, $viewer, $showClosed, $now, $respectSivo)) {
            return true;
        }

        return false;
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
     * @param bool                     $respectSivo
     * @return bool
     */
    public function isVisibleToUserOrChildOfUser(IAssessable $assessment, User $viewer, bool $showClosed, Alaress_Date_Format $now = null, bool $respectSivo = true): bool
    {
        $now = $now ?? Alaress_Date_Format::now();

        if ($this->isVisibleToUser($assessment, $viewer, $showClosed, $now, $respectSivo)) {
            return true;
        }
        foreach ($viewer->getChildren() as $child) {
            if ($this->isVisibleToUser($assessment, $child, $showClosed, $now, $respectSivo)) {
                return true;
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
    private function isVisibleToInstructor(IAssessable $assessable, User $instructor, bool $showClosed, Alaress_Date_Format $now): bool
    {
        if ($showClosed) {
            return true;
        }
        // If instructor is also a participant, respect their custom close date.
        $participant = $assessable->getParticipantIfExtant($instructor);
        $closeDate   = $participant ? $participant->getCloseDate() : $assessable->getCloseAt();
        return $now->lt($closeDate);
    }


    /**
     * Is the assessable visible to a staff member? (Assumes the user has a staff role type)
     *
     * @param IAssessable         $assessable
     * @param User                $staff
     * @param bool                $showClosed Should we show assessments after their closed date?
     * @param Alaress_Date_Format $now
     * @return bool
     */
    private function isVisibleToStaff(IAssessable $assessable, User $staff, bool $showClosed, Alaress_Date_Format $now): bool
    {
        if ($showClosed) {
            return true;
        }
        // If staff member is also a participant, respect their custom close date.
        $participant = $assessable->getParticipantIfExtant($staff);
        $closeDate   = $participant ? $participant->getCloseDate() : $assessable->getCloseAt();
        return $now->lt($closeDate);
    }


    /**
     * Is the assessable visible to a non-instructor? (ie a user with DENY or READ access to the assessment's folder context)
     *
     * @param IAssessable         $assessable
     * @param User                $nonInstructor
     * @param bool                $showClosed Should we show assessments after their closed date?
     * @param Alaress_Date_Format $now
     * @param bool                $respectSivo
     * @return bool
     */
    private function isVisibleToNonInstructor(IAssessable $assessable, User $nonInstructor, bool $showClosed, Alaress_Date_Format $now, bool $respectSivo): bool
    {
        if ($respectSivo && $assessable->isStaffOrInstructorVisibleOnly() && !$nonInstructor->getRole()->isStaff()) {
            // Staff can always see SIVO assessments, regardless of folder perm
            return false;
        }
        $participant = $assessable->getParticipantIfExtant($nonInstructor);
        if (!$participant) {
            return false;
        }
        $closeDate   = $participant->getCloseDate();
        $isPublished = $now->gte($participant->getPublishDate());
        return $isPublished && ($showClosed || $now->lte($closeDate));
    }
}
