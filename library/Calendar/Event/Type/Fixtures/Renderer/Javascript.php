<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Fixtures\Renderer;

class Javascript extends Base
{

    /**
     * Visitor
     *
     * @return string
     */
    public function render(): string
    {
        /* The event type, which is the list on the far-right hand
         * column of the Filters modal
         */
        $classes = [
            'type' . $this->event->getType(),
        ];

        /* IF this comes from some kind of subscription, make it obey the toggle
         */
        $connectorId = (int)$this->event->getConnectorId();

        if ($connectorId > 0) {
            $classes[] = 'source' . $connectorId;
        }

        /* Flatten the representation into a standardised structure
         */
        $event = [
            'title'     => $this->event->getTitle(),
            'start'     => $this->event->getStartTime()->getDatabase(),
            'end'       => null,

            /* Whether you can edit this event or not
             */
            'editable'  => false,

            /* Whether it's an all-day event or not
             * If allDay is true, then you can't resize it
             */
            'allDay'    => false,

            /* Colour of the event
             */
            'color'     => $this->event->getColour(),

            /* Campus slug so you can filter events
             */
            'className' => implode(' ', $classes),

            /* Extra data that the Event Object doesn't directly support
             */
            'data'      => [
                'meta'       => [
                    'editable'    => false,
                    'eventId'     => $this->event->getEventId(),
                    'time'        => $this->event->getTime(),
                    'detail'      => '',
                    'location'    => $this->event->getLocation(),
                    'eventTypeId' => $this->event->getType(),
                    'eventType'   => 'type' . $this->event->getType(),
                    'variant'     => $this->event->getEventVariant(),
                    'type'        => $this->event->getEventType(),
                    'author'      => null,
                    'authorId'    => null,
                    'level'       => null,
                    'completed'   => null,
                ],
                'links'      => [
                    'category' => [
                        'id'   => '',
                        'name' => '',
                    ],
                ],
                'styles'     => [
                    'visible' => '',
                ],

                'attendance' => [
                    'icon'          => false,
                    'status'        => false,
                    'requestButton' => false,
                    'listButton'    => false,
                ],
                'custom'     => [
                    'modifyLink' => '',
                ],
            ],
        ];

        return json_encode($event, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
