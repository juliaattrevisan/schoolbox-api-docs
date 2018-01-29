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
     * Determines if the specified user can see an IAssessable.
     *
     * * Instructor  - can see anytime (so long as not past close and we are hiding closed)
     * * Learner     - can only see after publish date
     *
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
        // Are we hiding assessments past their close date and we are past the close date ?
        if (!$showClosed) {
            // Use the participant's date if the viewing user is a participant
            $participant = $assessment->getParticipantIfExtant($viewer);
            $closeDate   = $participant ? $participant->getCloseDate() : $assessment->getCloseAt();
            if (!$closeDate->isNull() && Alaress_Date_Format::now()->gte($closeDate)) {
                return false;
            }
        }

        $folderAccessLevel = $this->access->getFolderAccess($viewer, $assessment->getBaseContext()->getFolder()->getId());

        // Is viewer an instructor?
        if ($folderAccessLevel >= UserAccess::ACCESS_WRITE) {
            return true;
        }
        // Is viewer a learner?
        if ($folderAccessLevel === UserAccess::ACCESS_READ) {
            // Is viewer a participant, and assessment is published for them?
            $participant = $assessment->getParticipantIfExtant($viewer);
            return $participant && Alaress_Date_Format::now()->gte($participant->getPublishDate());
        }
        return false;
    }
}
