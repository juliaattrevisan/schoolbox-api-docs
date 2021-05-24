<?php

/**
 * PendingInPrincipleReview
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup\Type;

use EOTC\EventStatusMapper;
use EOTC\ListGroup\Response;

class PendingInPrincipleReview extends Base
{

    /**
     * Next status id
     *
     * @var int
     */
    protected $nextStatusId = EventStatusMapper::STATUS_INPRINCIPLE;

    /**
     * Determine where to go next
     *
     * @return $this|mixed
     */
    public function determine()
    {

        /* All Draft (Initial Application) events get promoted to this
         */
        if ($this->getEvent()->getMetadata()->getStatus()->isDraft()) {
            return new Response($this->getNextStatusId(), $this->getMapping()->getPendingInPrincipleReviewIndex());
        }

        /* Otherwise get the next class to deal with it
         */
        if ($this->getNext()) {
            return $this->getNext()->determine();
        }

        return null;
    }
}
