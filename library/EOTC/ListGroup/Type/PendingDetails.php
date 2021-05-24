<?php

/**
 * PendingDetails
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup\Type;

use EOTC\EventStatusMapper;
use EOTC\ListGroup\Response;

class PendingDetails extends Base
{

    /**
     * Next status id, based on RAMS requirement
     *
     * @var int
     */
    protected $nextStatusId = EventStatusMapper::STATUS_DETAILSCOMPLETED;

    /**
     * Alternate next status id, based on RAMS requirement
     *
     * @var int
     */
    private $alternateNextStatusId = EventStatusMapper::STATUS_AWAITINGFINALAPPROVAL;

    /**
     * Determine where to go next
     *
     * @return Response|Base
     */
    public function determine()
    {

        /* All events which are Approved In Principle go into
         * the Pending Details list group
         */
        if ($this->getEvent()->getMetadata()->getStatus()->isInPrinciple()) {
            if ($this->getEvent()->getMetadata()->getRamsRequired()) {
                return new Response($this->getNextStatusId(), $this->getMapping()->getPendingDetailsReviewIndex());
            } else {
                return new Response($this->getAlternateNextStatusId(), $this->getMapping()->getPendingDetailsReviewIndex());
            }
        }

        /* Otherwise get the next class to deal with it
         */
        if ($this->getNext()) {
            return $this->getNext()->determine();
        }

        return null;
    }

    /**
     * Set alternate next status id
     *
     * @param int $alternateNextStatusId
     */
    private function setAlternateNextStatusId($alternateNextStatusId)
    {
        $this->alternateNextStatusId = $alternateNextStatusId;
    }

    /**
     * Get alternate next status id
     *
     * @return int
     */
    private function getAlternateNextStatusId()
    {
        return $this->alternateNextStatusId;
    }
}
