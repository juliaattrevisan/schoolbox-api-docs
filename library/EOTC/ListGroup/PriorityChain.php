<?php

/**
 * PriorityChain
 *
 * Almost identical to Chain, but instead of putting an event into a
 * particular Event List Group, it has a special check on the start
 * datetime to force it into a special Event List Group called Urgent
 *
 * Otherwise it proceeds ordinarily
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup;

use EOTC\Event;

class PriorityChain
{

    /**
     * @var Response
     */
    private $result;

    /**
     * Constructor
     *
     * @param \EOTC\Event $event
     */
    public function __construct(Event $event)
    {

        /* Set up chain of responsibility
         */
        $urgent = new Type\Urgent($event);

        /* Start motor
         */
        $this->setResult($urgent->determine());
    }

    /* Getters/Setters
     */

    /**
     * Set result
     *
     * @param string $result
     */
    private function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * @return string
     */
    public function getResult()
    {
        return $this->result;
    }
}
