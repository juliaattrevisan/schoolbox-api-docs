<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar\Events;

use Alaress_Date_Format;
use Calendar\Event\Collection;
use Calendar\Event\Type\EventInterface;
use Calendar\Event\Type\Submission\Event;
use Calendar_Api_EventType_Interface;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Implementation of the calendar API for the assessment calendar.
 */
class Api implements Calendar_Api_EventType_Interface
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
     * @inheritDoc
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        $assessments = $this->assessmentDataSource->findAllBetweenDates(
            Alaress_Date_Format::newFromTimestamp($startTimestamp),
            Alaress_Date_Format::newFromTimestamp($endTimestamp)
        );

        $stack = new Collection();
        foreach ($assessments as $assessment) {
            $event = $this->transformAssessmentToEvent($assessment);
            if ($event) {
                $stack->add($event);
            }
        }
        return $stack;
    }


    /**
     * @inheritDoc
     */
    public function getEvent($eventId)
    {
        $stack = new Collection();

        try {
            $assessment = $this->assessmentDataSource->findById($eventId);
            $event      = $this->transformAssessmentToEvent($assessment);
            if ($event) {
                $stack->add($event);
            }
        } catch (MapperException $e) {
            // noop
        }
        return $stack;
    }


    /**
     * Helper for converting an assessment to an event.
     *
     * TODO consider putting this somewhere common, where the other assessment event renderers can use it
     *
     * @param IAssessable $assessment
     * @return EventInterface|null
     */
    private function transformAssessmentToEvent(IAssessable $assessment): ?EventInterface
    {
        $dueDate = $assessment->getDueAt();

        // Can't show an event if I don't know when to show it
        if (!$dueDate) {
            return null;
        }

        $event = new Event();

        $event->setAssessment($assessment);

        $event->setStartTime($dueDate);
        $event->setEndTime($dueDate);

        // Will only be all day if the event's date is one second before
        // midnight.
        $event->setAllDay($dueDate->isAtEndOfDay());

        // All assessment events can be populated identically now
        $event->setEventId($assessment->getId());
        $event->setTitle($assessment->getTitle());
        $event->setDetail($assessment->getDescription());
        $event->setPublishDate($assessment->getPublishAt());
        $event->setResourceId($assessment->getId());

        return $event;
    }
}
