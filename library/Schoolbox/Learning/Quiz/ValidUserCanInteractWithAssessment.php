<?php
/**
 * ValidUserCanInteractWithAssessment
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz;

use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;

class ValidUserCanInteractWithAssessment
{
    /** @var Alaress_Date_Format */
    private $now;
    /** @var IAssessable */
    private $assessable;
    /** @var Access */
    private $accessHelper;


    /**
     * @param Alaress_Date_Format $now
     * @param IAssessable         $assessable
     * @param Access              $accessHelper
     */
    public function __construct(Alaress_Date_Format $now, IAssessable $assessable, Access $accessHelper)
    {
        $this->now = $now;
        $this->assessable = $assessable;
        $this->accessHelper = $accessHelper;
    }


    /**
     * @param User $user
     * @return bool
     * @throws \UnexpectedValueException
     */
    public function isSatisfiedBy(User $user)
    {
        /**
            If you are an active (included) participant {
                If within plan date range {
                    return true;
                }

                return false;
            }

            // Not a participant, but you have write access to the page
            If (access >= write) {
                // You've been excluded
                if (!excluded) {
                    return true;
                }
            }

            return false;
         */

        /* If you were a participant but you got excluded
         * you can't get in, no matter who you are
         */
        if ($this->assessable->isParticipant($user)) {
            if (!$this->assessable->getParticipant($user)->isExcluded()) {
                $plan = $this->assessable->getParticipant($user)->getPlan();

                /* When a user doesn't have a customised plan, these dates are null
                 * Use default assessment dates in that case
                 */
                $open = $plan->getOpenDate();
                $close = $plan->getCloseDate();

                if ($plan->getOpenDate()->isNull()) {
                    $open = $this->assessable->getOpenAt();
                }

                if ($plan->getCloseDate()->isNull()) {
                    $close = $this->assessable->getCloseAt();
                }

                return $this->now->gte($open) && $this->now->lt($close);
            }

            return false;
        }

        /* If you aren't a participant but you're an instructor that hasn't been excluded, you can get in
         */
        if ($this->accessHelper->isInstructor($user, $this->assessable->getContext()->getFolder())) {
            return true;
        }

        return false;
    }
}
