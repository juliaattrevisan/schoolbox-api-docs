<?php
/**
 * CalendarEventType
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar;

use JsonSerializable;

class EventType implements JsonSerializable
{

    /**
     * Event type id
     *
     * @var string
     */
    private $id = '';

    /**
     * Event type name
     *
     * @var string
     */
    private $type = '';

    /**
     * Colour of this event type
     *
     * @var string
     */
    private $color = '';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /* Getters/Setters
     */

    /**
     * Set color
     *
     * @param string $color
     * @return $this
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set id
     *
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Is this the "Task" event type?
     *
     * @return bool
     */
    public function isTaskType()
    {
        return $this->getType() == 'Task';
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'id'    => $this->id,
            'name'  => $this->type,
            'color' => $this->color,
        ];
    }
}
