<?php
/**
 * Renders out a JSON blob for day event objects, this is used by full calendar
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Calendar\Event\Type\Day\Renderer;

class Javascript extends Base
{
    public function render()
    {
        if ($this->isVisible()) {

            /* Future events are visible to superusers, event owners, group members with write access
             */
            if ($this->isTranslucent()) {
                $cssClass = 'translucent';
            } else {
                $cssClass = '';
            }

            /* Event attendance
             */
            $hasIcon = false;
            $hasStatus = false;
            $hasAttendanceRequestButton = false;
            $hasListButton = false;

            if ($this->event->canAttend()) {
                $hasIcon = true;

                /* Anyone with write access can approve attendance
                 */
                if ($this->event->isEditable()) {
                    $hasListButton = true;
                }

                $hasStatus = $this->attendanceStatus();

                if ($hasStatus) {
                    $hasAttendanceRequestButton = 'Unattend';
                } else {
                    $hasAttendanceRequestButton = 'RSVP';
                }
            }

            /* Congeal all that stuff
             */
            $start = $this->event->getStartTime()->getDatabaseDate();
            $end = $this->event->getEndTime()->getDatabaseDate();
            $all_day = $this->event->getAllDay();
            if (!$all_day) {
                $start .= ' ' . $this->event->getStartTime()->getDatabaseTime();
                $end .= ' ' . $this->event->getEndTime()->getDatabaseTime();
            } else {
                // FullCalendar (and vCal) 2.0 treats event ends as exclusive.
                // Doesn't really matter for timed events, but all day events
                // get truncated by one day.
                $end = $this->event->getEndTime()->addDays(1)->getDatabaseDate();
            }

            //
            // Add campus list to list of classes
            $classes = $this->getEvent()->getEventClasses();

            $event = array(
                'title'     => $this->event->getTitle(),
                'start'     => $start,
                'end'       => $end,

                /* Whether you can edit this event or not
                 */
                'editable'  => $this->event->isEditable(),

                /* Whether it's an all-day event or not
                 * If allDay is true, then you can't resize it
                 */
                'allDay'    => $all_day,

                /* Colour of the event
                 */
                'color'     => $this->event->getColour(),

                /* Campus slug so you can filter events
                 */
                'className' => $classes,

                /* Extra data that the Event Object doesn't directly support
                 */
                'data'      => array(
                    'meta'       => array(
                        'eventId'     => $this->event->getEventId(),
                        'time'        => $this->event->getTime(),
                        'detail'      => \Scrub::html($this->event->getDetail()),
                        'location'    => $this->event->getLocation(),
                        'eventTypeId' => $this->event->getType(),
                        'eventType'   => 'type' . $this->event->getType(),
                        'type'        => $this->event->getEventType(),
                        'author'      => $this->event->getAuthorName(),
                        'authorId'    => $this->event->getAuthorId(),
                        'level'       => $this->event->getEventClasses(),
                        'completed'   => $this->event->getCompleted(),
                        'seriesId'    => $this->event->getSeriesId(),
                    ),
                    'links'      => array(
                        'category' => array(
                            'id'   => $this->event->getCategoryId(),
                            'name' => $this->event->getCategoryName(),
                        )
                    ),
                    'styles'     => array(
                        'visible' => $cssClass,
                    ),
                    'attendance' => array(
                        'icon'          => $hasIcon,
                        'status'        => $hasStatus,
                        'requestButton' => $hasAttendanceRequestButton,
                        'listButton'    => $hasListButton,
                    )
                )
            );

            return json_encode($event) . ',';
        }
    }


}
