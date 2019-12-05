<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Mggs\Calendar\Event\Type\Submission\Renderer;

use Aura\Di\ContainerInterface;
use Calendar\Event\Type\Submission;
use Calendar\Event\Type\Submission\Renderer\Javascript as SubmissionRendererJavascript;
use Custom\Mggs\Personalised\Participant\Mapper as ParticipantMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use UnexpectedValueException;

class Javascript extends SubmissionRendererJavascript
{
    /** @var ParticipantMapper $pblMapper */
    private $pblMapper;

    /**
     * Constructor
     *
     * @param ContainerInterface $di
     * @param Submission\Event   $event
     * @param User               $user
     * @param User               $viewer
     */
    public function __construct(ContainerInterface $di, Submission\Event $event, User $user, User $viewer)
    {
        parent::__construct($di, $event, $user, $viewer);

        // Regrettably, because of dynamic invocation, we have to settle for singletons in here

        $this->pblMapper = ParticipantMapper::getInstance();
    }

    /**
     * Helper function to determine if person viewing assessment event can edit it, this should probably be on the
     * event itself but its not so this will help
     *
     * @return bool
     */
    public function isEditable()
    {
        if ($this->getEvent()->getAssessment() instanceof Project) {
            // Cannot move projects in this view

            return false;
        }

        // Can edit if staff, or student is viewing own work

        return $this->getViewer()->getRole()->isStaff() || $this->getViewer()->getId() === $this->getUser()->getId();
    }

    /**
     * @return array
     */
    protected function deriveCore()
    {
        $event = $this->getEvent();

        try {
            // Obtain the reference to "start time" via PBL

            $dueDate = $this->pblMapper->findOneByColumnList([
                'assessment_id' => $this->getEvent()->getAssessment()->getId(),
                'student_id'    => $this->getUser()->getId(),
            ])->getOpenDate();
        } catch (MapperException $e) {
            // No override: fall back to the due date of the assessment

            $dueDate = $event->getAssessment()->getDueAt();
        }

        // Congeal all that stuff

        $allDay = $event->getAllday();

        if ($event->getAllday()) {
            $start = $dueDate->getDatabaseDate();
        } else {
            $start = $dueDate->getDatabase();
        }

        $context = $event->getAssessment()->getContext();

        if ($context instanceof Project) {
            // ResourceId is class-unit-project

            $contextId = $context->getContext()->getId();
            $projectId = $context->getId();
        } else {
            // ResourceId is class-unit-0

            $contextId = $context->getId();
            $projectId = 0;
        }

        $resourceId = sprintf('%d-%d-%d', $event->getCategory()->getId(), $contextId, $projectId);

        return [
            'title' => $event->getTitle(),
            'start' => $start,
            'end'   => '',

            // Whether you can edit this event or not

            'editable' => $this->isEditable(),

            // Whether it's an all-day event or not If allDay is true, then you can't resize it
            // Schoolbox usage would be that all events are not allDay events

            'allDay' => $allDay,

            // Colour of the event

            'color'      => $event->getColour(),
            'className'  => $event->getEventClasses(),
            'resourceId' => $resourceId,
        ];
    }

    /**
     * @return array
     */
    protected function deriveMeta()
    {
        $meta = parent::deriveMeta();

        // Pass through the bounds allowed for student's movement

        $meta['bounds'] = [
            'open' => $this->getEvent()->getAssessment()->getOpenAt()->getDatabaseDate(),
            'due'  => $this->getEvent()->getAssessment()->getDueAt()->getDatabaseDate(),
        ];

        $status = $this->getStatus();

        // Has this student completed this assessment?

        $meta['status']       = $status;
        $meta['completed']    = $status === 'reviewed';
        $meta['assessmentId'] = $this->getEvent()->getAssessment()->getId();
        $meta['isProject']    = $this->getEvent()->getAssessment() instanceof Project;

        return $meta;
    }

    protected function deriveStyles()
    {
        $styles = parent::deriveStyles();

        $styles["status-{$this->getStatus()}"] = true;

        return $styles;
    }

    /**
     * @return string
     * @throws UnexpectedValueException
     */
    protected function getStatus()
    {
        // Add a style for bling (if you're not a parent)

        if ($this->getViewer()->getRole()->isParent()) {
            return null;
        }

        $part = $this->getEvent()->getAssessment()->getParticipantIfExtant($this->getUser());

        if (!$part) {
            // If there's no participation, there's no bling

            return null;
        }

        if ($part->isResponseRequired()) {
            return 'incomplete';
        }

        if ($part->isFeedbackRequired()) {
            return 'pending';
        }

        // did the student master this task?
        $fb = $part->getAuthoritativeFeedback(true);

        if (!$fb) {
            // Erm, this shouldn't happen, but ok?

            return 'pending';
        }

        $mark = $fb->getNormalisedMark();

        // Determine which threshold we need to respect

        if ($this->getEvent()->getAssessment()->getContext() instanceof Project) {
            $threshold = 'pbl_project_threshold';
        } else {
            $threshold = 'pbl_mastery_threshold';
        }

        if ($mark === null || $mark >= ConfigLoader::getInstance()->get($threshold)) {
            // Mark as success if null, or above mastery level

            return 'reviewed';
        }

        // Disappoint

        return 'failed';
    }
}
