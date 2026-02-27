<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar\Events;

use Alaress\Color\Color;
use Alaress_Date_Format;
use Calendar\Event\Type\EventInterface;
use Calendar\Event\Type\Submission\Event;
use Calendar_Api_EventType_Interface;
use Schoolbox\Filter\Filters\Assessment\IdFinder;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlan;
use Schoolbox\Learning\Assessment\Reporting\Model as Reporting;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use UnexpectedValueException;

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
     * @var null|Visibility
     */
    private $visibility;

    /**
     * @var IdFinder
     */
    private $assessmentIdFinder;

    /**
     * @var array
     */
    private $filters;

    /**
     * @var User
     */
    private $user;

    /**
     * @var User
     */
    private $viewer;

    /**
     * The user whose assessment visibility rules should decide whether events are included.
     *
     * This can differ from $viewer for Upcoming Due Work. Parents need parent-specific work type visibility applied,
     * while staff viewing a student's Upcoming calendar should still see the student's due-work set rather than every
     * staff-visible assessment for that student.
     *
     * @var User
     */
    private $visibilityViewer;

    /**
     * @var bool
     */
    private $isRestrictParentLessonPlanAccess;

    /**
     * Constructor
     *
     * @param User                 $user
     * @param User                 $viewer
     * @param AssessmentDataSource $assessmentDataSource
     * @param null|Visibility      $visibility
     * @param IdFinder             $assessmentIdFinder
     * @param array                $filters
     * @param bool                 $isRestrictParentLessonPlanAccess
     * @param User|null            $visibilityViewer
     */
    public function __construct(
        User $user,
        User $viewer,
        AssessmentDataSource $assessmentDataSource,
        ?Visibility $visibility,
        IdFinder $assessmentIdFinder,
        array $filters,
        bool $isRestrictParentLessonPlanAccess,
        ?User $visibilityViewer = null
    ) {
        $this->assessmentDataSource = $assessmentDataSource;
        $this->visibility           = $visibility;
        $this->assessmentIdFinder   = $assessmentIdFinder;
        $this->user                 = $user;
        $this->viewer               = $viewer;
        $this->visibilityViewer     = $visibilityViewer ?? $viewer;
        $this->filters              = $filters;
        $this->isRestrictParentLessonPlanAccess = $isRestrictParentLessonPlanAccess;
    }


    /**
     * @inheritDoc
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): GenericCollection
    {
        $assessments = $this->assessmentDataSource->findAllBetweenDates(
            Alaress_Date_Format::newFromTimestamp($startTimestamp),
            Alaress_Date_Format::newFromTimestamp($endTimestamp),
            $this->visibility ? $this->user : null,
            $this->assessmentIdFinder->findIdsByFilters($this->filters, $this->user)
        );

        if ($this->visibility) {
            // User's participation can be assumed if they exist
            $this->assessmentDataSource->loadParticipantsIntoAssessmentCollection(
                collection: $assessments,
                users: (new \Schoolbox\Collection())->add($this->user),
                removeExcluded: true,
                assumeParticipation: true
            );
        }

        $stack = new GenericCollection(EventInterface::class);
        foreach ($assessments as $assessment) {
            if ($this->visibility && !$this->visibility->isVisibleToUser($assessment, $this->visibilityViewer, true)) {
                continue;
            }

            /* Reporting activities have no meaning for students who shouldn't see anything about staff-only activities.
             */
            if (!$this->viewer->getRole()->isStaff() && $assessment instanceof Reporting) {
                continue;
            }

            /**
             * If a parent is viewing, they should only see their child's lesson plans
             * if the restrict_parent_lesson_plan_access config setting is enabled
             */
            $isParentOfUser = $this->viewer->isParentOf($this->user);
            $isLessonPlan = $assessment instanceof LessonPlan;
            if ($isParentOfUser && $isLessonPlan && $this->isRestrictParentLessonPlanAccess) {
                continue;
            }

            $event = $this->transformAssessmentToEvent($assessment, $this->user);
            if ($event) {
                $stack->add($event);
            }
        }
        return $stack;
    }


    /**
     * @inheritDoc
     */
    public function getEvent($eventId): GenericCollection
    {
        $stack = new GenericCollection(EventInterface::class);

        try {
            $assessment = $this->assessmentDataSource->findById($eventId);
            if ($this->visibility) {
                $assessments = new GenericCollection(IAssessable::class, [$assessment]);
                $this->assessmentDataSource->loadParticipantsIntoAssessmentCollection(
                    $assessments,
                    $this->user,
                    true,
                    true
                );
                $assessment = $assessments->first();
            }
            if ($this->visibility && !$this->visibility->isVisibleToUser($assessment, $this->visibilityViewer, true)) {
                return $stack;
            }
            $event = $this->transformAssessmentToEvent($assessment, $this->user);
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
     * @param User        $student
     * @return EventInterface|null
     */
    private function transformAssessmentToEvent(IAssessable $assessment, User $student): ?EventInterface
    {
        try {
            $participation = $assessment->getParticipant($student);
            $dueDate = $participation->getDueDate();
            $description = $participation->getDescription();
        } catch (UnexpectedValueException $e) {
            $dueDate = $assessment->getDueAt();
            $description = $assessment->getDescription();
        }


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
        $event->setDetail($description);
        $event->setPublishDate($assessment->getPublishAt());
        $event->setResourceId($assessment->getId());
        $folderColour = $assessment->getContext()->getFolder()->getColour();
        if ($folderColour instanceof Color) {
            $event->setColour($folderColour->getAsRgba());
        }
        return $event;
    }
}
