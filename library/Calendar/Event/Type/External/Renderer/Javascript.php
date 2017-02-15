<?php
/**
 * Renders out a JSON blob for day event objects, this is used by full calendar
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Calendar\Event\Type\External\Renderer;

class Javascript extends Base
{
    public function render()
    {
        if ($this->isVisible()) {


            /* Event attendance
             */
            $hasIcon = false;
            $hasStatus = false;
            $hasAttendanceRequestButton = false;


            // Add campus list to list of classes
            $classes = $this->getEvent()->getEventClasses();
            $event = array(
                'title'     => $this->event->getTitle(),
                'start'     => $this->event->getStartTime()->getDatabase(),
                'end'       => $this->event->getEndTime()->getDatabase(),

                /* Whether you can edit this event or not
                 */
                'editable'  => false,

                /* Whether it's an all-day event or not
                 * If allDay is true, then you can't resize it
                 */
                'allDay'    => $this->event->getAllday(),

                /* Colour of the event
                 */
                'color'     => $this->event->getColour(),

                /* Campus slug so you can filter events
                 */
//                'className' => $classes,

                /* Extra data that the Event Object doesn't directly support
                 */
                'data'      => array(
                    'meta'       => array(
                        'eventId'     => $this->event->getEventId(),
                        'time'        => $this->event->getTime(),
                        'detail'      => \Scrub::html($this->event->getDetail()),
                        'location'    => $this->event->getLocation(),
                        'room'        => $this->event->getRoom(),
                        'eventTypeId' => $this->event->getType(),
                        'eventType'   => 'type' . $this->event->getType(),
                        'type'        => $this->event->getEventType(),
                        'author'      => $this->event->getAuthorName(),
                        'authorId'    => $this->event->getAuthorId(),
                        'level'       => $this->event->getEventClasses(),
                    ),
//                    'links'      => array(
//                        'category' => array(
//                            'id'   => $this->event->getCategoryId(),
//                            'name' => $this->event->getCategoryName(),
//                        )
//                    ),
                    'styles'     => array(
                        'visible' => $cssClass,
                    ),
                    'attendance' => array(
                        'icon'          => $hasIcon,
                        'status'        => $hasStatus,
                        'listButton' => $hasListButton,
                        'requestButton'    => $hasAttendanceRequestButton,
                    )
                )
            );

            return json_encode($event) . ',';
        }
    }


}
