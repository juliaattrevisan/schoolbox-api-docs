<?php

/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup\Type;

use EOTC\Event;
use EOTC\EventStatusMapper;
use EOTC\ListGroup\Mapping;

abstract class Base
{

    /**
     * A chain node
     *
     * @var Base
     */
    protected $next;

    /**
     * An instance of \EOTC\Event
     *
     * @var \EOTC\Event
     */
    protected $event;

    /**
     * An instance of \EOTC\ListGroup\Mapping
     *
     * @var \EOTC\ListGroup\Mapping
     */
    protected $mapping;

    /**
     * eotc_status primary key
     *
     * This is the next status in the sequence
     *
     * @var int
     */
    protected $nextStatusId = EventStatusMapper::STATUS_INPRINCIPLE;

    /**
     * @param \EOTC\Event $event
     */
    public function __construct(Event $event)
    {
        $this->setEvent($event);
        $this->setMapping(new Mapping());

        $this->setup();
    }

    /**
     * Determine what the next status is, and where the
     * event should currently be grouped
     *
     * @return Base
     */
    abstract public function determine();

    /**
     * Hook method
     */
    protected function setup()
    {
    }

    /* Getters/Setters
     */

    /**
     * Set event
     *
     * @param \EOTC\Event $event
     */
    protected function setEvent($event)
    {
        $this->event = $event;
    }

    /**
     * Get event
     *
     * @return \EOTC\Event
     */
    protected function getEvent()
    {
        return $this->event;
    }

    /**
     * Set next
     *
     * @param Base|None $next
     */
    public function setNext($next)
    {
        $this->next = $next;
    }

    /**
     * Get next
     *
     * @return Base|None
     */
    public function getNext()
    {
        return $this->next;
    }

    /**
     * Set mapping
     *
     * @param \EOTC\ListGroup\Mapping $mapping
     */
    protected function setMapping($mapping)
    {
        $this->mapping = $mapping;
    }

    /**
     * Get mapping
     *
     * @return \EOTC\ListGroup\Mapping
     */
    protected function getMapping()
    {
        return $this->mapping;
    }

    /**
     * Set next status id
     *
     * @param int $nextStatusId
     */
    protected function setNextStatusId($nextStatusId)
    {
        $this->nextStatusId = $nextStatusId;
    }

    /**
     * Get next status id
     *
     * @return int
     */
    protected function getNextStatusId()
    {
        return $this->nextStatusId;
    }
}
