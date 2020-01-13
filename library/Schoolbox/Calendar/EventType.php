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
use Schoolbox\ISoftDeletable;
use Schoolbox\Sequential;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class EventType implements ISoftDeletable, JsonSerializable, Sequential
{
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\CreateCollection;
    use ModelTrait\HasSequence;

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
     * @var int
     */
    private $updatedBy;

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
     * @return int|null
     */
    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    /**
     * @param int|null $updatedBy
     * @return $this
     */
    public function setUpdatedBy(?int $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'id'    => $this->id,
            'type'  => $this->type,
            'color' => $this->color,
        ];
    }
}
