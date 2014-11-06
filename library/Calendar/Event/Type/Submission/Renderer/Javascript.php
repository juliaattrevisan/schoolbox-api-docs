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
class Calendar_Event_Type_Submission_Renderer_Javascript extends Calendar_Event_Type_Renderer_Abstract
{

    /**
     * Visitor
     *
     * @param $currentEvent
     */
    public function visit($currentEvent)
    {
        if ($this->isVisible($currentEvent)) {
            /* Future events are visible to superusers, event owners, group members with write access
             */
//            if ($currentEvent->getPublishDate()->getUnixTimestamp() >= time()) {
//                $cssClass = 'translucent';
//            } else {
//                $cssClass = '';
//            }

            /* Modify link. Should be templated...
             */
            if ($currentEvent->getIsEditable() && ($currentEvent->getEventId() > -1)) {
                $editable = true;
            } else {
                $editable = false;
            }

            /* Show a homepage if appropriate
             */
            if ($currentEvent->getCategoryId() > 0) {
                $categoryId   = $currentEvent->getCategoryId();
                $categoryName = $currentEvent->getCategoryName();
            } else {
                $categoryId   = '';
                $categoryName = '';
            }

            /* Event attendance
             */
            $hasIcon                    = false;
            $hasStatus                  = false;
            $hasAttendanceRequestButton = false;
            $hasListButton              = false;

            /* Congeal all that stuff
             */
            $start   = $currentEvent->getStartTime()->getDatabaseDate();
            $all_day = $currentEvent->getAllDay();
            if (!$all_day) {
                $start .= ' ' . $currentEvent->getStartTime()->getDatabaseTime();
            }

            // Set classes
            $classes = array(
                'group' . $categoryId,
                'type' . $currentEvent->getType(),
            );
            if (($connectorId = $currentEvent->getConnectorId()) > 0) {
                $classes[] = 'source' . $connectorId;
            }

            $event = array(
                'title'     => $currentEvent->getTitle(),
                'start'     => "$start",
                'end'       => "",
                /* Whether you can edit this event or not
                 */
                'editable'  => $editable,
                /* Whether it's an all-day event or not
                 * If allDay is true, then you can't resize it
                 *
                 * Schoolbox usage would be that all events are not
                 * allDay events
                 */
                'allDay'    => $all_day,
                /* Colour of the event
                 */
                'color'     => $currentEvent->getColour(),
                'className' => $classes,
                /* Extra data that the Event Object doesn't directly support
                 */
                'data'      => array(
                    'meta'       => array(
                        'eventId'   => $currentEvent->getEventId(),
                        'time'      => $currentEvent->getTime(),
                        'detail'    => Scrub::html($currentEvent->getSubmissionBoxDetail()),
                        'location'  => $currentEvent->getLocation(),
                        'eventType' => 'type' . $currentEvent->getType(),
                        'type'      => $currentEvent->getEventType(),
                        'author'    => $currentEvent->getAuthorName(),
                        'authorId'  => $currentEvent->getAuthorId(),
                        'level'     => $currentEvent->getDerivedEventLevel(),
                        'completed' => $currentEvent->getCompleted(),
                    ),
                    'links'      => array(
                        'category' => array(
                            'id'   => $categoryId,
                            'name' => $categoryName,
                        )
                    ),
                    'styles'     => array(
                        'visible' => $this->isVisible($currentEvent),
                    ),
                    'attendance' => array(
                        'icon'          => $hasIcon,
                        'status'        => $hasStatus,
                        'requestButton' => $hasAttendanceRequestButton,
                        'listButton'    => $hasListButton,
                    )
                )
            );

            echo json_encode($event);
            echo ",";
        }
    }
}
