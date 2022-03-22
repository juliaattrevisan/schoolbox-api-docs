<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\SudsTimetable\Renderer;

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

        /* Apparently this is a place to put a link to More Details
         * which spawns in the frontend under custom > moreDetailsLink...
         * ...I think?
         */

        /* Try to find a matching folder for this event's code
         * If a folder exists, link to that
         * Otherwise, leave it empty
         *
         * There isn't a convenient place to inject a mapper, sadly
         *
         * This class spawns dynamically in:
         * library/Calendar/Api/Event/Collection/Renderer.php:renderEvent()
         */
        try {
            $folder = \Schoolbox\Resource\Folder\Mapper::getInstance()->findByCode($this->event->getCode());

            $path = sprintf(
                '/homepage/code/%s',
                $folder->getCodeString()
            );

            /* If you link to a folder code, a checkbox appears in the calendar
             * Filter modal underneath Groups, allowing you to toggle it
             */
            $classes[] = 'group' . $folder->getId();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $path = null;
        }

        /* Ram in the Day and Period labels as part of the event title
         * Schools sometimes use this terminology colloquially, rather
         * than just the datetimes, to refer to when things happen
         *
         * However, the Day and Period strings might be null or otherwise
         * meaningless, so filter and implode as best you can
         */
        $title = implode(', ', array_filter([
            $this->event->getDay(),
            $this->event->getPeriod(),
            $this->event->getTitle()
        ], static function ($val) {
            return '' !== trim($val);
        }));

        /* Flatten the representation into a standardised structure
         */
        $event = [
            'title'     => $title,
            'start'     => $this->event->getStartTime()->getDatabase(),
            'end'       => $this->event->getEndTime()->getDatabase(),

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
                    'detail'      => $this->event->getDetail(),
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
                    'moreDetailsLink' => $path
                ],
            ],
        ];

        return json_encode($event, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
