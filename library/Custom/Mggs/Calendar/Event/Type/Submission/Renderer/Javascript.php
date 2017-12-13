<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Mggs\Calendar\Event\Type\Submission\Renderer;

use Calendar;
use Calendar\Event\Type\EventBase;
use Group;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\User;
use SitePage;


class Javascript extends \Calendar\Event\Type\Submission\Renderer\Javascript
{
    /** @var \Schoolbox\Learning\Personalised\Participant\Mapper */
    private $pblMapper;


    /**
     * @param SitePage  $page
     * @param EventBase $event
     * @param User      $user
     * @param Group     $groupHandler
     * @param Calendar  $calHandler
     * @param User      $viewer
     */
    public function __construct(SitePage $page, EventBase $event, User $user, Group $groupHandler, Calendar $calHandler, User $viewer)
    {
        parent::__construct($page, $event, $user, $groupHandler, $calHandler, $viewer);

        // regrettably, because of dynamic invocation, we have to settle for singletons in here
        $this->pblMapper = \Schoolbox\Learning\Personalised\Participant\Mapper::getInstance();
    }


    /**
     * Helper function to determine if person viewing assessment event can edit it, this should probably be on the
     * event itself but its not so this will help
     * @return bool
     */
    public function isEditable()
    {
        // can edit if staff, or student is viewing own work
        return $this->getViewer()->getRole()->isStaff() || $this->getViewer()->getId() === $this->getUser()->getId();
    }


    /**
     * @return array
     */
    protected function deriveCore()
    {
        $event = $this->getEvent();

        try {
            // obtain the reference to "start time" via PBL
            $dueDate = $this->pblMapper->findOneByColumnList([
                'assessment_id' => $this->getEvent()->getAssessment()->getId(),
                'student_id'    => $this->getUser()->getId()
            ])->getOpenDate();
        } catch (\Schoolbox\Mapper\Exception $e) {
            // no override: fall back to the due date of the assessment
            $dueDate = $event->getAssessment()->getDueAt();
        }

        /* Congeal all that stuff
         */
        $allDay = $event->getAllday();

        if ($event->getAllday()) {
            $start = $dueDate->getDatabaseDate();
        } else {
            $start = $dueDate->getDatabase();
        }

        $context = $event->getAssessment()->getContext();

        if ($context instanceof Project) {
            // resourceId is class-unit-project
            $contextId = $context->getContext()->getId();
            $projectId = $context->getId();
        } else {
            // resourceId is class-unit-0
            $contextId = $context->getId();
            $projectId = 0;
        }

        $resourceId = sprintf('%d-%d-%d', $event->getCategory()->getId(), $contextId, $projectId);

        return [
            'title' => $event->getTitle(),
            'start' => $start,
            'end'   => '',

            /* Whether you can edit this event or not
             */
            'editable' => $this->isEditable(),

            /* Whether it's an all-day event or not
             * If allDay is true, then you can't resize it
             *
             * Schoolbox usage would be that all events are not
             * allDay events
             */
            'allDay' => $allDay,

            /* Colour of the event
             */
            'color'     => $event->getColour(),
            'className' => $event->getEventClasses(),
            'resourceId' => $resourceId
        ];
    }


    /**
     * @return array
     */
    protected function deriveMeta()
    {
        $meta = parent::deriveMeta();

        // pass through the bounds allowed for student's movement
        $meta['bounds'] = [
            'open' => $this->getEvent()->getAssessment()->getOpenAt()->getDatabaseDate(),
            'due'  => $this->getEvent()->getAssessment()->getDueAt()->getDatabaseDate()
        ];

        // has this student completed this assessment?
        $meta['completed'] = true;

        return $meta;
    }

    protected function deriveStyles()
    {
        $styles = parent::deriveStyles();

        // TODO if student has completed, add a class for reducing its visibility

        return $styles;
    }
}