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
     * @param int $startTimestamp
     * @param int $endTimestamp
     * @return Collection|Event[]
     * @throws DomainException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        $stack = new Collection();
        // Configuration may not have been set, take this into account.
        // TODO - this is wrong!
        try {
            $eventType = $this->loadConnectorConfiguration()->decodeConfig()->getLuEventType();
        } catch (Schoolbox\Mapper\Exception $e) {
            $eventType = 1;
        }

        // Find for student currently hacks together a term
        $term = (new Schoolbox\Assessment\Term\Model())
            ->setStart(new Alaress_Date_Format($startTimestamp, Alaress_Date_Format::TIMESTAMP))
            ->setActualFinish(new Alaress_Date_Format($endTimestamp, Alaress_Date_Format::TIMESTAMP));

        // Get a timeline of events
        // Use the assessment data source to get everything for this user

        if ($this->getUser()->getRole()->isStudent()) {
            $assessables = $this->assessmentDataSource->findAllByStudent($this->getUser(), true, $term);
        } else {
            $assessables = $this->assessmentDataSource->findAllByStaff($this->getUser(), $term);
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
            $event->setStartTime($dueDate);
            $event->setEndTime($dueDate);

            // Will only be all day if the event's date is one second before
            // midnight.
            $event->setAllDay($dueDate->isAtEndOfDay());

            // All assessment events can be populated identically now
            $event->setEventId($assessment->getId());
            $event->setTitle($assessment->getTitle());
            $event->setDetail($participant? $participant->getDescription() : $assessment->getDescription());
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
     * @param $eventId
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
