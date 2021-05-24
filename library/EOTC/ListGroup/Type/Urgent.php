<?php

/**
 * Urgent
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup\Type;

use EOTC\ListGroup\Chain;
use EOTC\ListGroup\Response;
use EOTC\ConfigMapper;

class Urgent extends Base
{

    /**
     * Determine where to go next
     *
     * @return Response
     */
    public function determine()
    {
        $difference = $this->getEvent()->getStartDateTime()->getUnixTimeStamp() - $_SERVER['REQUEST_TIME'];

        /* Find out what the event's next status should be
         */
        $chain = new Chain($this->getEvent());
        $location = $chain->getResult();

        /* 3 days before start of event: throw it into the Urgent category
         * regardless of what its current status is (unless it's already
         * Approved/Rejected, at which point no further action is necessary)
         */
        $isApproved = $this->getEvent()->getMetadata()->getStatus()->isApproved();
        $isRejected = $this->getEvent()->getMetadata()->getStatus()->isRejected();

        if ($this->isWithinThreshold($difference) && !$isApproved && !$isRejected) {
            return new Response($location->getNextStatus(), $this->getMapping()->getUrgentIndex());
        }

        /* Not urgent; proceed as normal
         */
        return $location;
    }

    /**
     * Determine whether you're within a certain threshold of
     * a particular timestamp
     *
     * @param int $timeDifference
     * @return bool
     */
    private function isWithinThreshold($timeDifference)
    {
        $configMapper = ConfigMapper::getInstance();
        $config = $configMapper->findUrgentThreshold();

        /* N days before the event starts causes an event to be
         * put into Urgent subcategory
         */
        if ($timeDifference < 60 * 60 * 24 * (int)$config->getValue()) {
            return true;
        }

        return false;
    }
}
