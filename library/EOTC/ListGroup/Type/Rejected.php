<?php

/**
 * PendingInitialReview
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup\Type;

use EOTC\EventStatusMapper;
use EOTC\ListGroup\Response;

class Rejected extends Base
{

    /**
     * Next status id
     *
     * @var int
     */
    protected $nextStatusId = EventStatusMapper::STATUS_REJECTED;

    /**
     * Determine where to go next
     *
     * @return $this|mixed
     */
    public function determine()
    {

        /* All Rejected events end up in their own feedback cycle
         */
        if (($this->getEvent()->getMetadata()->getStatus()->isRejected())) {
            return new Response($this->getNextStatusId(), $this->getMapping()->getRejectedIndex());
        }

        /* Otherwise get the next class to deal with it
         */
        if ($this->getNext()) {
            return $this->getNext()->determine();
        }

        return null;
    }
}
