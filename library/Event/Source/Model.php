<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Event\Source;

use Schoolbox\Calendar\Event;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

/**
 * Class Model
 *
 * @package Event\Source
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * An event found in our event table
     *
     * @var Event
     */
    private $event;


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
     * @return Event
     */
    public function getEvent()
    {
        return $this->event;
    }


    /**
     * @param Event $event
     * @return $this
     */
    public function setEvent($event)
    {
        $this->event = $event;
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
