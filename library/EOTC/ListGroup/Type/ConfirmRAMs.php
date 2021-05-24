<?php

/**
 * AwaitingFinalApproval
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup\Type;

use EOTC\EventStatusMapper;
use EOTC\ListGroup\Response;

class ConfirmRAMs extends Base
{

    /**
     * Next status id
     *
     * @var int
     */
    protected $nextStatusId = EventStatusMapper::STATUS_AWAITINGFINALAPPROVAL;

    /**
     * Determine where to go next
     *
     * @return $this|null
     */
    public function determine()
    {

        /* This comprises events which came from:
         *
         * RAMS Approved (RAMS Required)
         */
        if ($this->getEvent()->getMetadata()->getStatus()->isDetailsCompleted()) {
            return new Response($this->getNextStatusId(), $this->getMapping()->getConfirmRAMsReviewIndex());
        }

        /* Otherwise get the next class to deal with it
         */
        if ($this->getNext()) {
            return $this->getNext()->determine();
        }

        return null;
    }
}
