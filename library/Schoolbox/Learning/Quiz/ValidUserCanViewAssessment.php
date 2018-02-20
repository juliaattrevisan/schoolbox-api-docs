<?php
/**
 * ValidUserCanViewAssessment
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

class ValidUserCanViewAssessment
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
    public function isSatisfiedBy(User $user): bool
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
                return true;
            }

            return false;
         */

        if ($this->assessable->isParticipant($user) && !$this->assessable->getParticipant($user)->isExcluded()) {
            $plan = $this->assessable->getParticipant($user)->getPlan();

            /* When a user doesn't have a customised plan, these dates are null
             * Use default assessment dates in that case
             */
            $publish = $plan->getPublishDate();
            $close = $plan->getCloseDate();

            if ($plan->getPublishDate()->isNull()) {
                $publish = $this->assessable->getPublishAt();
            }

            if ($plan->getCloseDate()->isNull()) {
                $close = $this->assessable->getCloseAt();
            }

            return $this->now->gte($publish) && $this->now->lt($close);
        }

        if ($this->accessHelper->isInstructor($user, $this->assessable->getContext()->getFolder())) {
            return true;
        }

        return false;
    }
}
