<?php

/**
 * PendingFinalApproval
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup\Type;

use EOTC\EventStatusMapper;
use EOTC\ListGroup\Response;

class AwaitingFinalApproval extends Base
{

    /**
     * Next status id
     *
     * @var int
     */
    protected $nextStatusId = EventStatusMapper::STATUS_APPROVED;

    /**
     * Determine where to go next
     *
     * @return $this|null
     */
    public function determine()
    {

        /* If it's Awaiting Final Approval, next status is Approved
         */
        if ($this->getEvent()->getMetadata()->getStatus()->isAwaitingFinalApproval()) {
            return new Response($this->getNextStatusId(), $this->getMapping()->getAwaitingFinalApprovalReviewIndex());
        }

        /* Otherwise get the next class to deal with it
         */
        if ($this->getNext()) {
            return $this->getNext()->determine();
        }

        return null;
    }
}
