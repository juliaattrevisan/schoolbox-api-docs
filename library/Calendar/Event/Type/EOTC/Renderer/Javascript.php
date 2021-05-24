<?php

/**
 * Calendar_Event_Type_EOTC_Renderer_Javascript
 *
 * Renders out a JSON mob of event objects
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\EOTC\Renderer;

class Javascript extends Base
{

    /**
     * Visitor
     *
     * @return string
     */
    public function render(): string
    {
        /* Events not in Approved state appear translucent
         */
        $customSettings = $this->getEvent()->getCustom();

        if ($customSettings['approved']) {
            $cssClass = '';
        } else {
            $cssClass = 'translucent';
        }

        /* Modify link. Should be templated...
         */
        if ($this->getEvent()->isEditable() && ($this->getEvent()->getEventId() > -1)) {
            $editable = true;
        } else {
            $editable = false;
        }

        /* Congeal all that stuff
         */

        /* All-day settings
         */
        if ($this->getEvent()->getAllDay()) {
            // Compensate for FullCalendar/vCal having exclusive end times
            $allDay = true;
            $start  = $this->getEvent()->getStartTime()->getDatabaseDate();
            $end    = $this->getEvent()->getEndTime()->addDays(1)->getDatabaseDate();
        } else {
            $allDay = false;
            $start  = $this->getEvent()->getStartTime()->getDatabase();
            $end    = $this->getEvent()->getEndTime()->getDatabase();
        }

        //
        // Add campus list to list of classes
        $classes = [
            $this->getEvent()->getDerivedEventLevel(),
            'type' . $this->getEvent()->getType(),
        ];
        if (($connectorId = $this->getEvent()->getConnectorId()) > 0) {
            $classes[] = 'source' . $connectorId;
        }

        $campus_list = array_filter(array_map('trim', explode(',', $this->getEvent()->getCampusList())));

        foreach ($campus_list as &$campus) {
            $campus = 'campus' . trim($campus);
        }

        unset($campus);

        if (!empty($campus_list)) {
            $classes[] = implode(' ', $campus_list);
        }

        $event = [
            'title'     => $this->getEvent()->getTitle(),
            'start'     => "$start",
            'end'       => "$end",

            /* Whether you can edit this event or not
             * EOTC events can't be moved on the calendar yet, but they do have
             * a modify button.
             */
            'editable'  => false,

            /* Whether it's an all-day event or not
             * If allDay is true, then you can't resize it
             */
            'allDay'    => $allDay,

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
                    'editable'    => $editable,
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
                    'level'       => $this->getEvent()->getDerivedEventLevel(),
                    'completed'   => $this->getEvent()->getCompleted(),
                ],
                'links'      => [
                    'category' => [
                        'id'   => '',
                        'name' => '',
                    ],
                ],
                'styles'     => [
                    'visible' => $cssClass,
                ],

                'attendance' => [
                    'icon'          => false,
                    'status'        => false,
                    'requestButton' => false,
                    'listButton'    => false,
                ],
                'custom'     => [
                    'modifyLink' => '/modules/eotc/manage/modify?eventID=' . $this->getEvent()->getEventId(),
                ],
            ],
        ];

        return json_encode($event, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
