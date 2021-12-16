<?php

/**
 * Calendar_Api_EventType_Submission_Schoolbox_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Event\Collection;
use Calendar\Event\Type\Submission\Event;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\User;

class Calendar_Api_EventType_Submission_Schoolbox_Default extends Calendar_Api_EventType_Abstract
{
    /** @var Calendar */
    private $calendarHandler;

    /** @var AssessmentDataSource */
    private $assessmentDataSource;

    /** @var Visibility */
    private $visibility;


    /**
     * Constructor
     *
     * @param int                    $connectorId
     * @param User                   $user
     * @param User                   $viewer
     * @param AssessmentDataSource   $assessmentDataSource
     * @param Visibility             $visibility
     */
    public function __construct(
        int $connectorId,
        User $user,
        User $viewer,
        AssessmentDataSource $assessmentDataSource,
        Visibility $visibility
    ) {
        parent::__construct($connectorId, $user, $viewer);
        $this->calendarHandler       = new Calendar();
        $this->assessmentDataSource  = $assessmentDataSource;
        $this->visibility            = $visibility;
    }

    /**
     * Gets the display color for an event.
     *
     * @param  Folder $folder
     * @param  string $eventType
     * @return string
     */
    protected function getEventColor(Folder $folder, $eventType): string
    {
        return $this->calendarHandler->displayColour($eventType);
    }


    /**
     * Get day events from this data source
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return Collection
     * @throws DomainException
     * @throws LogicException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): Collection
    {
        $stack = new Collection();
        // Configuration may not have been set, take this into account.
        try {
            $eventType = $this->loadConnectorConfiguration()->decodeConfig()->getLuEventType();
        } catch (Schoolbox\Mapper\Exception $e) {
            /* TODO Repair assumption about default luEventType id
             *  You can't really make this assumption, because someone could have
             *  soft-deleted the first luEventType record; arbitrarily assigning 1
             *  could put events into a deleted event type; instead, you could get
             *  the full set, then pop the first of its elements
             */
            $eventType = 1;
        }

        /* Do a detection on $allowedEventTypes, if provided and meaningful
         */
        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            if (!\in_array((int)$eventType, $allowedEventTypes, true)) {
                return $stack;
            }
        }

        // Get a timeline of events
        // Use the assessment data source to get everything for this user
        $from = Alaress_Date_Format::newFromTimestamp($startTimestamp);
        $to   = Alaress_Date_Format::newFromTimestamp($endTimestamp);
        if ($this->getUser()->getRole()->isStudent()) {
            $assessables = $this->assessmentDataSource->findAllByStudentBetweenDates(
                $this->getUser(),
                true,
                $from,
                $to
            );
        } else {
            $assessables = $this->assessmentDataSource->findAllByStaffBetweenDates($this->getUser(), $from, $to);
        }

        foreach ($assessables as $assessment) {
            // Permission check to see the assessment
            if (!$this->visibility->isVisibleToUser($assessment, $this->getUser(), true)) {
                continue;
            }

            // This may be invoked by both staff and students.
            // Students will see their plan's dates, teachers will see the real assessment dates.
            $participant = $assessment->getParticipantIfExtant($this->getUser());
            $dueDate     = $participant ? $participant->getDueDate() : $assessment->getDueAt();

            // Can't show an event if I don't know when to show it
            if (!$dueDate) {
                continue;
            }

            $event = new Event();

            $event->setAssessment($assessment);
            $event->setType($eventType);

            // Use the folder's color if it has one
            $folder = $assessment->getBaseContext()->getFolder();
            $color  = $folder->getColour();
            if ($color instanceof \Alaress\Color\Color) {
                $event->setColour($color->getAsRgba());
            } else {
                $event->setColour($this->getEventColor($folder, $eventType));
            }
            // Passing cloned date objects so that start and end date can be modified separately
            $startTime = clone $dueDate;
            $event->setStartTime($startTime);
            $endTime = clone $dueDate;
            $event->setEndTime($endTime);

            // Will only be all day if the event's date is one second before
            // midnight.
            $event->setAllDay($dueDate->isAtEndOfDay());

            // All assessment events can be populated identically now
            $event->setEventId($assessment->getId());
            $event->setTitle($assessment->getTitle());
            $event->setDetail($participant ? $participant->getDescription() : $assessment->getDescription());
            $event->setPublishDate($participant ? $participant->getPublishDate() : $assessment->getPublishAt());
            $event->setResourceId($assessment->getId());

            // TODO add an author field to assessment
//            $event->setAuthorId($assessment->get->getId());
//            $event->setAuthorName($owner->getFirstName() . ' ' . $owner->getLastName());
            $event->setConnectorId($this->getConnectorId());

            $stack->add($event);
        }

        return $stack;
    }

    /**
     * Find a particular event from this source
     *
     * @param int $eventId
     *
     * @return Collection
     */
    public function getEvent($eventId)
    {
        $collection = new Collection();

        /* Fill in your query result set here and populate $collection
         */

        return $collection;
    }
}
