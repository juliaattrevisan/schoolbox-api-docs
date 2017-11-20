<?php

/**
 * Calendar_Event_Type_Submission_Renderer_Javascript
 * Renders out a JSON mob of event objects
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Calendar\Event\Type\Submission\Renderer;

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
    public function render()
    {
        if (!$this->isVisible()) {
            return '';
        }

        /* Congeal all that stuff
         */
        $start = $this->getEvent()->getStartTime()->getDatabaseDate();
        $all_day = $this->getEvent()->getAllday();
        if (!$all_day) {
            $start .= ' ' . $this->getEvent()->getStartTime()->getDatabaseTime();
        }

        $modifyLink = $this->getRouteIfAuthorized(
            'learningAssessment.getUpdateAssessmentForm',
            [
                'assessment'   => $this->getEvent()->getAssessment(),
                'assessmentId' => $this->getEvent()->getEventId(),
            ]
        );

        if ($this->getUser()->getRole()->isStaff()) {
            $moreDetailsLink = $this->getRouteIfAuthorized(
                'learningAssessment.getAssessment',
                [
                    'assessment'   => $this->getEvent()->getAssessment(),
                    'assessmentId' => $this->getEvent()->getEventId(),
                ]
            );
        } else {
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
        $event = array(
            'title'     => $this->getEvent()->getTitle(),
            'start'     => $start,
            'end'       => "",
            /* Whether you can edit this event or not
             */
            'editable'  => $this->isEditable(),
            /* Whether it's an all-day event or not
             * If allDay is true, then you can't resize it
             *
             * Schoolbox usage would be that all events are not
             * allDay events
             */
            'allDay'    => $all_day,
            /* Colour of the event
             */
            'color'     => $this->getEvent()->getColour(),
            'className' => $this->getEvent()->getEventClasses(),
            /* Extra data that the Event Object doesn't directly support
             */
            'data'      => array(
                'meta'       => array(
                    'eventId'   => $this->getEvent()->getEventId(),
                    'time'      => $this->getEvent()->getTime(),
                    'detail'    => Scrub::html($this->getEvent()->getDetail()),
                    'location'  => '',
                    'eventType' => 'type' . $this->getEvent()->getType(),
                    'type'      => $this->getEvent()->getEventType(),
                    'author'    => $this->getEvent()->getAuthorName(),
                    'authorId'  => $this->getEvent()->getAuthorId(),
                    'level'     => $this->getEvent()->getEventClasses(),
                    'completed' => '',
                ),
                'links'      => array(),
                'styles'     => array(
                    'visible' => true,
                ),
                'attendance' => array(
                    'icon'          => false,
                    'status'        => false,
                    'requestButton' => false,
                    'listButton'    => false,
                ),
                'custom'     => array(
                    'modifyLink'      => $modifyLink,
                    'moreDetailsLink' => $moreDetailsLink,
                ),
            ),
        );

        if ($this->getEvent()->getResourceId()) {
            $event['resourceId'] = $this->getEvent()->getResourceId();
        }

        return json_encode($event, JSON_PARTIAL_OUTPUT_ON_ERROR) . ',';
    }
}
