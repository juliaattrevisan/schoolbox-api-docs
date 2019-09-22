<?php

/**
 * Calendar_Event_Type_Submission_Renderer_Javascript
 * Renders out a JSON mob of event objects
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Submission\Renderer;

use Alaress\Color\Color;
use Alaress\Color\ColorFactory;
use \Scrub;

/**
 * Class Javascript
 *
 * @method \Calendar\Event\Type\Submission\Event getEvent()
 */
class Javascript extends Base
{

    /**
     * Visitor
     *
     * @return string
     */
    public function render(): string
    {
        $event = $this->deriveCore();
        $event['data'] = [
            'meta'       => $this->deriveMeta(),
            'links'      => $this->deriveLinks(),
            'styles'     => $this->deriveStyles(),
            'attendance' => $this->deriveAttendance(),
            'custom'     => $this->deriveCustom()
        ];


        return json_encode($event, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }


    /**
     * @return array
     */
    protected function deriveCore()
    {
        $event = $this->getEvent();

        /* Congeal all that stuff
         */
        $start = $event->getStartTime()->getDatabaseDate();
        $allDay = $event->getAllday();
        if (!$allDay) {
            $start .= ' ' . $event->getStartTime()->getDatabaseTime();
        }

        $data = [
            'title' => $event->getTitle(),
            'start' => $start,
            'end' => '',

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
            'color' => $event->getColour(),
            'className' => $event->getEventClasses(),
        ];

        if ($this->getEvent()->getResourceId()) {
            $data['resourceId'] = $this->getEvent()->getResourceId();
        }

        return $data;
    }


    /**
     * @return array
     */
    protected function deriveMeta()
    {
        $event      = $this->getEvent();
        $assessable = $event->getAssessment();
        $workType   = $assessable->getWorkType();
        $folderColour = $assessable->getContext()->getFolder()->getColour();
        $eventColour = null;

        if ($folderColour) {
            $colour = new Color($folderColour->getRed(), $folderColour->getGreen(), $folderColour->getBlue(), $folderColour->getAlpha());
            $eventColour = $colour->getAsRgba();
        }

        return [
            'eventId'   => $event->getEventId(),
            'time'      => $event->getTime(),
            'detail'    => Scrub::html($event->getDetail()),
            'location'  => '',
            'eventType' => 'type' . $event->getType(),
            'type'      => $event->getEventType(),
            'author'    => $event->getAuthorName(),
            'authorId'  => $event->getAuthorId(),
            'level'     => $event->getEventClasses(),
            'completed' => false,
            'folderName'=> $assessable->getContext()->getFolder()->getTitle(),
            'folderId'  => $assessable->getContext()->getFolder()->getId(),
            'workType'  => $workType ? $workType->getName() : null,
            'workTypeId'=> $workType ? $workType->getId() : null,
            'weighted'  => $assessable->getWeight() ? 1 : 0,
            'assessmentType' => $assessable->getFamilyName(),
            'colour' => $eventColour,
        ];
    }


    /**
     * @return array
     */
    protected function deriveLinks()
    {
        return [];
    }


    /**
     * @return array
     */
    protected function deriveStyles()
    {
        return [
            'visible' => true,
        ];
    }


    /**
     * @return array
     */
    protected function deriveAttendance()
    {
        return [
            'icon'          => false,
            'status'        => false,
            'requestButton' => false,
            'listButton'    => false,
        ];
    }


    /**
     * @return array
     */
    protected function deriveCustom()
    {
        $modifyLink = $this->getRouteIfAuthorized(
            'learningAssessment.getUpdateAssessmentForm',
            [
                'assessment'   => $this->getEvent()->getAssessment(),
                'assessmentId' => $this->getEvent()->getEventId(),
            ]
        );

        $moreDetailsLink = '';
        if ($this->getUser()->getRole()->isStaff()) {
            $moreDetailsLink = $this->getRouteIfAuthorized(
                'learningAssessment.getStatus',
                [
                    'assessment'   => $this->getEvent()->getAssessment(),
                    'assessmentId' => $this->getEvent()->getEventId(),
                ]
            );
        }
        if (!$moreDetailsLink) {
            $moreDetailsLink = $this->getRouteIfAuthorized(
                'learningAssessment.getSummary',
                [
                    'assessment'   => $this->getEvent()->getAssessment(),
                    'assessmentId' => $this->getEvent()->getEventId(),
                    'studentId'    => $this->getUser()->getId(),
                    'student'      => $this->getUser(),
                ]
            );
        }

        return [
            'modifyLink'      => $modifyLink,
            'moreDetailsLink' => $moreDetailsLink,
        ];
    }

}
