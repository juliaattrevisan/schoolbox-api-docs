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

use Calendar\Event\Type\RendererBase;

class Javascript extends RendererBase
{

    /**
     * Visitor
     *
     * @param $this->getEvent()
     */
    public function render()
    {
        if ($this->isVisible($this->getEvent())) {
            /* Future events are visible to superusers, event owners, group members with write access
             */
//            if ($this->getEvent()->getPublishDate()->getUnixTimestamp() >= time()) {
//                $cssClass = 'translucent';
//            } else {
//                $cssClass = '';
//            }

            /* Modify link. Should be templated...
             */
            if ($this->getEvent()->getIsEditable() && ($this->getEvent()->getEventId() > -1)) {
                $editable = true;
            } else {
                $editable = false;
            }

            /* Show a homepage if appropriate
             */
            if ($this->getEvent()->getCategoryId() > 0) {
                $categoryId   = $this->getEvent()->getCategoryId();
                $categoryName = $this->getEvent()->getCategoryName();
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
            $start   = $this->getEvent()->getStartTime()->getDatabaseDate();
            $all_day = $this->getEvent()->getAllDay();
            if (!$all_day) {
                $start .= ' ' . $this->getEvent()->getStartTime()->getDatabaseTime();
            }

            // Set classes
            $classes = array(
                'group' . $categoryId,
                'type' . $this->getEvent()->getType(),
            );
            if (($connectorId = $this->getEvent()->getConnectorId()) > 0) {
                $classes[] = 'source' . $connectorId;
            }

            $event = array(
                'title'     => $this->getEvent()->getTitle(),
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
                'color'     => $this->getEvent()->getColour(),
                'className' => $classes,
                /* Extra data that the Event Object doesn't directly support
                 */
                'data'      => array(
                    'meta'       => array(
                        'eventId'   => $this->getEvent()->getEventId(),
                        'time'      => $this->getEvent()->getTime(),
                        'detail'    => Scrub::html($this->getEvent()->getSubmissionBoxDetail()),
                        'location'  => $this->getEvent()->getLocation(),
                        'eventType' => 'type' . $this->getEvent()->getType(),
                        'type'      => $this->getEvent()->getEventType(),
                        'author'    => $this->getEvent()->getAuthorName(),
                        'authorId'  => $this->getEvent()->getAuthorId(),
                        'level'     => $this->getEvent()->getDerivedEventLevel(),
                        'completed' => $this->getEvent()->getCompleted(),
                    ),
                    'links'      => array(
                        'category' => array(
                            'id'   => $categoryId,
                            'name' => $categoryName,
                        )
                    ),
                    'styles'     => array(
                        'visible' => $this->isVisible($this->getEvent()),
                    ),
                    'attendance' => array(
                        'icon'          => $hasIcon,
                        'status'        => $hasStatus,
                        'requestButton' => $hasAttendanceRequestButton,
                        'listButton'    => $hasListButton,
                    )
                )
            );

            return json_encode($event).',';
        }
    }
}
