<?php
/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Event\Source;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * An eventId found in our event table
     *
     * @var int
     */
    private $eventId;

    /**
     * An identifier string that identifies the source of an event e.g. "liss"
     *
     * @var string
     */
    private $sourceName;

    /**
     * A text string which uniquely identifies an event in the event source e.g. csv/ics/vcal file
     *
     * @var string
     */
    private $guid;


    /**
     * @return int
     */
    public function getEventId()
    {
        return $this->eventId;
    }


    /**
     * @param int $eventId
     * @return $this
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
        return $this;
    }


    /**
     * @return string
     */
    public function getSourceName()
    {
        return $this->sourceName;
    }


    /**
     * @param string $sourceName
     * @return $this
     */
    public function setSourceName($sourceName)
    {
        $this->sourceName = $sourceName;
        return $this;
    }


    /**
     * @return string
     */
    public function getGuid()
    {
        return $this->guid;
    }


    /**
     * @param string $guid
     * @return $this
     */
    public function setGuid($guid)
    {
        $this->guid = $guid;
        return $this;
    }
}