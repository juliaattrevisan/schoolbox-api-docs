<?php

/**
 * Interface that all calendar events should share for things we use across the entire collection
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type;

use Alaress_Date_Format;

interface EventInterface
{

    /**
     * Set author
     *
     * @param string $authorName
     */
    public function setAuthorName($authorName);

    /**
     * Get author
     *
     * @return string
     */
    public function getAuthorName();

    /**
     * Set author id
     *
     * @param int $authorId
     */
    public function setAuthorId($authorId);

    /**
     * Get author id
     *
     * @return int
     */
    public function getAuthorId();

    /**
     * Set colour
     *
     * @param string $colour
     */
    public function setColour($colour);

    /**
     * Get colour
     *
     * @return string
     */
    public function getColour();

    /**
     * Set detail
     *
     * @param string $detail
     */
    public function setDetail($detail);

    /**
     * Get detail
     *
     * @return string
     */
    public function getDetail();

    /**
     * Set event type
     *
     * @param string $eventType
     */
    public function setEventType($eventType);

    /**
     * Get event type
     *
     * @return string
     */
    public function getEventType();


    /**
     * Set title
     *
     * @param string $title
     */
    public function setTitle($title);

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle();

    /**
     * Get title for export
     * @return string
     */
    public function getTitleForExport();

    /**
     * Set type
     *
     * @param string $type
     */
    public function setType($type);

    /**
     * Get type
     *
     * @return string
     */
    public function getType();

    /**
     * Set event id
     *
     * @param string|int $eventId
     */
    public function setEventId($eventId);

    /**
     * Get event id
     *
     * @return string|int
     */
    public function getEventId();


    /**
     * Set end time
     *
     * @param Alaress_Date_Format $endTime
     */
    public function setEndTime(Alaress_Date_Format $endTime);

    /**
     * Get end time
     *
     * @return Alaress_Date_Format
     */
    public function getEndTime();

    /**
     * Set start time
     *
     * @param Alaress_Date_Format $startTime
     */
    public function setStartTime(Alaress_Date_Format $startTime);

    /**
     * Get start time
     *
     * @return Alaress_Date_Format
     */
    public function getStartTime();

    /**
     * Is event an all day event
     *
     * @param bool $allday
     */
    public function setAllDay($allday);

    /**
     * Is event an all day event
     *
     * @return bool
     */
    public function getAllday();


    /**
     * Get renderer class
     *
     * @param string $format
     * @throws \Exception If unable to find a rendering class for the specified format
     * @return string
     */
    public function getRendererClass($format): string;

    /**
     * Set isEditable
     *
     * @param bool $isEditable
     */
    public function setIsEditable($isEditable);

    /**
     * Get isEditable
     *
     * @return bool
     */
    public function isEditable();

    /**
     * Set connector id
     *
     * @param string $connectorId
     */
    public function setConnectorId($connectorId);

    /**
     * Get connector id
     *
     * @return string
     */
    public function getConnectorId();

    /**
     * Gets classes to append for event display
     *
     * @return string
     */
    public function getEventClasses();

    /**
     * This can be overridden to append specific classes for the event
     * @return mixed
     */
    public function getFilterClasses();

    /**
     * Get location
     *
     * @return string
     */
    public function getLocation();


    /**
     * @return int|null
     */
    public function getMaxAttendees(): ?int;


    /**
     * @param int|null $maxAttendees
     * @return $this
     */
    public function setMaxAttendees(?int $maxAttendees): EventInterface;


    /**
     * @return bool
     */
    public function getActive(): bool;


    /**
     * @param bool $active
     * @return $this
     */
    public function setActive(bool $active): EventInterface;
}
