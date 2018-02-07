<?php
/**
 * Visibility
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
     * Visibility constructor.
     *
     * @param Access $access
     */
    public function __construct(Access $access)
    {
        $this->access = $access;
    }


    /**
     * Determines if the specified user can see an IAssessable (but not necessarily its details or participant interactions)
     *
     * * Staff       - can see anytime (so long as not past close and we are hiding closed)
     * * Instructor  - can see anytime (so long as not past close and we are hiding closed)
     * * Learner     - can only see if a participant (or parent of participant), and after publish date
     *
     * Staff      - is considered any user with role-type of 'staff'
     * Instructor - is considered any user with WRITE+ perm to folder
     * Learner    - is considered any user with READ perm to folder
     *
     * @param IAssessable $assessment Must have plans and participants loaded if viewer is a learner
     * @param User        $viewer
     * @param bool        $showClosed Should we exclude assessments if their close date has passed?
     * @return bool
     */
    public function isVisibleToUser(IAssessable $assessment, User $viewer, bool $showClosed): bool
    {
        $now = Alaress_Date_Format::now();

        /** @noinspection NotOptimalIfConditionsInspection */
        if ($viewer->getRole()->isStaff() && $this->isVisibleToStaff($assessment, $viewer, $showClosed, $now)) {
            return true;
        }

        // Is viewer an instructor?
        $folderAccessLevel = $this->access->getFolderAccess($viewer, $assessment->getBaseContext()->getFolder()->getId());
        if ($folderAccessLevel >= UserAccess::ACCESS_WRITE) {
            if ($this->isVisibleToInstructor($assessment, $viewer, $showClosed, $now)) {
                return true;
            }
        } // Is viewer a learner?
        elseif ($folderAccessLevel === UserAccess::ACCESS_READ) {
            // Is the viewer a participant themselves?
            if ($this->isVisibleToLearner($assessment, $viewer, $showClosed, $now)) {
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
    public function isVisibleToInstructor(IAssessable $assessable, User $instructor, bool $showClosed, Alaress_Date_Format $now): bool
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
    public function isVisibleToStaff(IAssessable $assessable, User $staff, bool $showClosed, Alaress_Date_Format $now): bool
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
     * Is the assessable visible to the Learner? (Assumes user is a learner)
     *
     * @param IAssessable         $assessable
     * @param User                $learner
     * @param bool                $showClosed Should we show assessments after their closed date?
     * @param Alaress_Date_Format $now
     * @return bool
     */
    public function isVisibleToLearner(IAssessable $assessable, User $learner, bool $showClosed, Alaress_Date_Format $now): bool
    {
        $participant = $assessable->getParticipantIfExtant($learner);
        if (!$participant || $participant->isExcluded()) {
            return false;
        }
        $closeDate   = $participant->getCloseDate();
        $isPublished = $now->gte($participant->getPublishDate());
        return $isPublished && ($showClosed || $now->lte($closeDate));
    }
}
