<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar\Events;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Calendar\Event\Type\Submission\Event;
use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;

// Can this be used?

class Controller extends Base
{
    /**
     * @var AssessmentDataSource
     */
    private $assessmentDataSource;

    /**
     * Constructor
     *
     * @param AssessmentDataSource $assessmentDataSource
     */
    public function __construct(AssessmentDataSource $assessmentDataSource)
    {
        $this->assessmentDataSource = $assessmentDataSource;
    }


    /**
     * Returns the events for the assessment calendar, as a JSON feed.
     *
     * @api   GET /learning/assessment/calendar/events
     * @since 19.1
     */
    public function getEvents(): void
    {
        // Must have:
        // start and end dates, not greater than 2 months between?
        $startParam = $this->request->query->get('start');
        $endParam   = $this->request->query->get('end');
        if (!isset($startParam)) {
            // Raise a HTTP problem
            return;
        }
        if (!isset($endParam)) {
            // Raise a HTTP problem
            return;
        }
        try {
            $start = Alaress_Date_Format::newFromTimestamp($this->request->query->get('start'));
        } catch (InvalidArgumentException $e) {
            // Raise a HTTP problem
            return;
        }
        try {
            $end = Alaress_Date_Format::newFromTimestamp($this->request->query->get('end'));
        } catch (InvalidArgumentException $e) {
            // Raise a HTTP problem
            return;
        }


        $events      = new GenericCollection(Event::class);
        $assessments = $this->assessmentDataSource->findAllBetweenDates($start, $end);
        foreach ($assessments as $assessment) {

            $participant = $assessment->getParticipantIfExtant($this->getUser());
            $dueDate     = $participant ? $participant->getDueDate() : $assessment->getDueAt();

            // Can't show an event if I don't know when to show it
            if (!$dueDate) {
                continue;
            }

            $event = new Event();

            $event->setAssessment($assessment);

//            $event->setType($eventType);

            // Use the folder's color if it has one
//            $folder = $assessment->getBaseContext()->getFolder();
//            $color  = $folder->getColour();
//            if ($color instanceof \Alaress\Color\Color) {
//                $event->setColour($color->getAsRgba());
//            } else {
//                $event->setColour($this->getEventColor($folder, $eventType));
//            }
            $event->setStartTime($dueDate);
            $event->setEndTime($dueDate);

            // Will only be all day if the event's date is one second before
            // midnight.
            $event->setAllDay($dueDate->isAtEndOfDay());

            // All assessment events can be populated identically now
            $event->setEventId($assessment->getId());
            $event->setTitle($assessment->getTitle());
            $event->setDetail($participant ? $participant->getDescription() : $assessment->getDescription());
            $event->setPublishDate($participant ? $participant->getPublishDate() : $assessment->getPublishAt());
            $event->setResourceId($assessment->getId());

            // TODO add an author field to assessment?
//            $event->setAuthorId($assessment->get->getId());
//            $event->setAuthorName($owner->getFirstName() . ' ' . $owner->getLastName());
//            $event->setConnectorId($this->getConnectorId());

            $events->add($event);
        }

        // Use the renderer to... render them
        // Sadly all the renderers use Sitepages: let's end this
    }
}
