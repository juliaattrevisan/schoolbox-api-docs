<?php

/**
 * Renders out a JSON blob for day event objects, this is used by full calendar
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\External\Renderer;

class Javascript extends Base
{
    public function render(): string
    {
        /* Event attendance
         */
        $hasIcon                    = false;
        $hasStatus                  = false;
        $hasListButton              = false;
        $hasAttendanceRequestButton = false;

        $classes = [];

        if (($connectorId = $this->getEvent()->getConnectorId()) > 0) {
            $classes[] = 'source' . $connectorId;
        }

        $classes[] = 'type' . $this->getEvent()->getType();

        $event = [
            'title'     => $this->getEvent()->getTitle(),
            'start'     => $this->getEvent()->getStartTime()->getDatabase(),
            'end'       => $this->getEvent()->getEndTime()->getDatabase(),

            /* Whether you can edit this event or not
             */
            'editable'  => false,

            /* Whether it's an all-day event or not
             * If allDay is true, then you can't resize it
             */
            'allDay'    => false,

            /* Colour of the event
             */
            'color'     => $this->getEvent()->getColour(),

            /* Campus slug so you can filter events
             */
            'className' => implode(' ', $classes),

            /* Extra data that the Event Object doesn't directly support
             */
            'data'      => [
                'meta'       => [
                    'eventId'     => $this->getEvent()->getEventId(),
                    'time'        => $this->getEvent()->getTime(),
                    'detail'      => \Scrub::html($this->getEvent()->getDetail()),
                    'location'    => $this->getEvent()->getLocation(),
                    'eventTypeId' => $this->getEvent()->getType(),
                    'eventType'   => 'type' . $this->getEvent()->getType(),
                    'variant'     => $this->getEvent()->getEventVariant(),
                    'type'        => $this->getEvent()->getEventType(),
                    'author'      => $this->getEvent()->getAuthorName(),
                    'authorId'    => $this->getEvent()->getAuthorId(),
                    'level'       => $this->getEvent()->getEventClasses(),
                    'completed'   => false,
                ],
                // Do I need this? TODO
//                    'links'      => array(
//                    'category' => array(
//                    'id'   => $this->event->getCategoryId(),
//                    'name' => $this->event->getCategoryName(),
//                        )
//                    ),
                'styles'     => [
                    'visible' => '',
                ],
                'attendance' => [
                    'icon'          => $hasIcon,
                    'status'        => $hasStatus,
                    'listButton'    => $hasListButton,
                    'requestButton' => $hasAttendanceRequestButton,
                ],
            ],
        ];

        return json_encode($event, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
