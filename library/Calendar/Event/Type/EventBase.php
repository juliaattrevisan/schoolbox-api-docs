<?php

/**
 * The is the base class that all events inherit it is the minimal set of functions for all events
 * Do not add anything in here unless it is shared by all events
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Type;

use Alaress_Date_Format;

abstract class EventBase implements EventInterface
{
    /**
     * Event title
     *
     * @var string
     */
    protected $title = '';

    /**
     * Unique event identifier
     * @var string|int
     */
    protected $eventId = -1;

    /**
     * Start date and time of event
     *
     * @var Alaress_Date_Format
     */
    protected $startTime = null;

    /**
     * End date and time of event
     * @var Alaress_Date_Format
     */
    protected $endTime = null;

    /**
     * The date when the event was published (made visible to users)
     * Stored always as an Alaress_Date_Format() instance
     *
     * @var Alaress_Date_Format
     */
    protected $publishDate = null;



    /**
     * Event description, typically
     *
     * @var string
     */
    protected $detail = '';

    /** Event type ID
     * @var int
     */
    protected $typeID = -1;

    /** eventType is a human-friendly description of an event's type;
     * e.g. Event, Excursion, Report etc. and comes from a lookup table
     * @var string
     */
    protected $eventType = '';

    /**
     * The ID of the calendar connector this event came from
     *
     * @var int
     */
    protected $connectorId;

    /**
     * Author's name
     *
     * @var string
     */
    protected $authorName = '';

    /**
     * Author's id
     *
     * @var int
     */
    protected $authorId = -1;

    /** A hex string representing the colour of this event
     * @var string
     */
    protected $colour = '';

    /**
     * The class used to render this event object. This should be
     * just the class' name, not an actual instance of it
     *
     * @var array
     */
    protected $renderClasses = array(   'javascript'    => RendererBase::class,
                                        'html'          => RendererBase::class,
                                        'vcal'          => VObjectBase::class,
                                    );

    /**
     * Whether or not to display the modify link
     *
     * @var bool
     */
    protected $isEditable = true;


    /**
     * Is this an all day event?
     *
     * @var bool
     */
    protected $allday = false;

    /**
     * Is this event in a series? If it has a series ID, it is.
     *
     * @var int
     */
    protected $seriesId = null;

    /**
     * Constructor
     *
     * Set up default settings
     */
    public function __construct()
    {
        $this->setup();
    }


    /**
     * Hook method for your own customisations
     *
     * Empty by default
     */
    protected function setup()
    {
    }

    /**
     * Find out if the currently logged in user is the owner (creator)
     * of this event
     *
     * @return bool
     */
    public function isOwner()
    {
        if ($this->getAuthorId() == $_SESSION['id']) {
            return true;
        }

        return false;
    }

    /**
     * Get a string representing the full span of time for this event
     * This is used in the calendar dashboard
     *
     * @return string
     * @deprecated
     */
    public function getEventSpan()
    {
        if ($this->getStartTime()->getDatabaseDate() != $this->getEndTime()->getDatabaseDate()) {
            if ($this->getAllDay()) {
                return '';
            }
            return $this->getStartTime()->getCalendarDateTime() . '&ndash;' . $this->getEndTime()->getCalendarDateTime();
        } else {
            if ($this->getAllDay()) {
                return '';
            }
            return $this->getStartTime()->getTimeOnly() . '&ndash;' . $this->getEndTime()->getTimeOnly();
        }
    }

    /**
     * Gets date of event in format like this: Thursday, 19th December, 2:00AM
     *
     * @return string
     */
    public function getFullDate()
    {
        $startDate = $this->getStartTime()->getLongDateOnly();
        if (!$this->getAllDay()) {
            $formattedTime = $startDate . ', ' . $this->getTime();
        } else {
            $formattedTime = $startDate;
        }
        return $formattedTime;
    }

    /**
     * Special time formattng
     *
     * @return string
     */
    public function getTime()
    {
        if ($this->getAllDay()) {
            return false;
        } else {
            return $this->getStartTime()->getTimeOnly();
        }
    }

    /**
     * Classes used for filter selectors
     *
     * @return string
     */
    public function getEventClasses()
    {
        $selectors = array(
            'source' . $this->getConnectorId(),
        );
        $type = $this->getType();
        if ($type > 0) {
            $selectors[] = 'type'.$type;
        }

        /* Merge levels
         */
        $allLevels = explode(' ', $this->getFilterClasses());
        $selectors = array_merge($selectors, $allLevels);


        /* Properly trim out spaces and stuff
         */
        foreach ($selectors as &$currentSelector) {
            $currentSelector = trim($currentSelector);
        }

        $selectors = array_filter(array_unique($selectors));

        return implode(' ', $selectors);
    }

    /**
     * Event visibility; generates a CSS string
     *
     * @return string
     */
    public function getFilterClasses() {
        return '';
    }

    /* Getters/Setters
     */

    /**
     * Set author
     *
     * @param $authorName
     */
    public function setAuthorName($authorName)
    {
        $this->authorName = $authorName;
    }

    /**
     * Get author
     *
     * @return null
     */
    public function getAuthorName()
    {
        return $this->authorName;
    }

    /**
     * Set author id
     *
     * @param $authorId
     */
    public function setAuthorId($authorId)
    {
        $this->authorId = $authorId;
    }

    /**
     * Get author id
     *
     * @return null
     */
    public function getAuthorId()
    {
        return $this->authorId;
    }

    /**
     * Set colour
     *
     * @param $colour
     */
    public function setColour($colour)
    {
        if ($colour[0] != '#') {
            $this->colour = '#' . $colour;
        } else {
            $this->colour = $colour;
        }
    }

    /**
     * Get colour
     *
     * @return null
     */
    public function getColour()
    {
        return $this->colour;
    }

    /**
     * Set detail
     *
     * @param $detail
     */
    public function setDetail($detail)
    {
        $this->detail = $detail;
    }

    /**
     * Get detail
     *
     * @return null
     */
    public function getDetail()
    {
        return $this->detail;
    }

    /**
     * Set event type
     *
     * @param $eventType
     */
    public function setEventType($eventType)
    {
        $this->eventType = $eventType;
    }

    /**
     * Get event type
     *
     * @return null
     */
    public function getEventType()
    {
        return $this->eventType;
    }



    /**
     * Set title
     *
     * @param $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Get title
     *
     * @return null
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set type
     *
     * @param int $typeID
     */
    public function setType($typeID)
    {
        $this->typeID = $typeID;
    }

    /**
     * Get type
     *
     * @return int
     */
    public function getType()
    {
        return $this->typeID;
    }

    /**
     * Set event id
     *
     * @param int|string $eventId
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * Get event id
     *
     * @return int|string
     */
    public function getEventId()
    {
        return $this->eventId;
    }


    /**
     * Set end time
     *
     * @param Alaress_Date_Format $endTime
     */
    public function setEndTime(Alaress_Date_Format $endTime)
    {
        $this->endTime = $endTime;
    }

    /**
     * Get end date and time
     *
     * @return Alaress_Date_Format
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * Set start date and time
     *
     * @param Alaress_Date_Format $startTime
     */
    public function setStartTime(Alaress_Date_Format $startTime)
    {
        $this->startTime = $startTime;
    }

    /**
     * Get start date and time
     *
     * @return Alaress_Date_Format
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * Set renderer class
     *
     * @param string $rendererClass
     */
    public function setRendererClass($renderClasses)
    {
        $this->renderClasses = $renderClasses;
    }

    /**
     * Get renderer class
     *
     * @param string
     * @return string
     */
    public function getRendererClass($type)
    {
        if($this->renderClasses[$type]) {
            return $this->renderClasses[$type];
        }
        else {
            throw new \Exception("Unable to find rendering class for $type");
        }

    }

    /**
     * Set isEditable
     *
     * @param bool $isEditable
     */
    public function setIsEditable($isEditable)
    {
        $this->isEditable = $isEditable;
    }

    /**
     * Get isEditable
     *
     * @return bool
     */
    public function isEditable()
    {
        return $this->isEditable;
    }

    /**
     * Set connector id
     *
     * @param string $connectorId
     */
    public function setConnectorId($connectorId)
    {
        $this->connectorId = $connectorId;
    }

    /**
     * Get connector id
     *
     * @return string
     */
    public function getConnectorId()
    {
        return $this->connectorId;
    }

    /**
     * @param bool $allday
     */
    public function setAllday($allday)
    {
        $this->allday = $allday;
    }

    /**
     * @return bool
     */
    public function getAllday()
    {
        return $this->allday;
    }

    /**
     * Set publish date object
     *
     * @param Alaress_Date_Format $publishDate
     */
    public function setPublishDate($publishDate)
    {
        if ($publishDate instanceof Alaress_Date_Format) {
            $this->publishDate = $publishDate;
        } elseif ($publishDate == null) {
            $this->publishDate = new \Alaress_Date_NullDate();
        } else {
            $this->publishDate = new Alaress_Date_Format($publishDate, 'Y-m-d H:i:s');
        }
    }

    /**
     * Get publish date
     *
     * @return Alaress_Date_Format
     */
    public function getPublishDate()
    {
        return $this->publishDate;
    }

    /**
     * @return int
     */
    public function getSeriesId()
    {
        return $this->seriesId;
    }

    /**
     * @param int $seriesId
     */
    public function setSeriesId($seriesId)
    {
        $this->seriesId = $seriesId;
    }

    /**
     * Cloning magic, when expanding multiday events we use clone, this ensures they have independent dates
     */
    public function __clone() {
        if (is_object($this->startTime)) {
            $this->startTime = clone $this->startTime;
        }
        if (is_object($this->endTime)) {
            $this->endTime = clone $this->endTime;
        }
        if (is_object($this->publishDate)) {
            $this->publishDate = clone $this->publishDate;
        }
    }

}
