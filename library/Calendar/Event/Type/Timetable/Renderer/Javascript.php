<?php
/**
 * Renders out a JSON blob for day event objects, this is used by full calendar
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Timetable\Renderer;

class Javascript extends Base
{
    public function render(): string
    {
        /* Event attendance
         */
        $hasIcon = false;
        $hasStatus = false;
        $hasAttendanceRequestButton = false;

        /* Output list button */
        if ($this->showAttendanceLink()) {
            $encodedClass = urlencode(urlencode(urlencode($this->getEvent()->getCode())));
            $classLink = $this->getEvent()->getCampus() ? $this->getEvent()->getCampus() . '/' . $encodedClass : '0/' . $encodedClass;

            $hasListButton = sprintf(
                "<a class='button icon-class-list' href='/modules/attendance/modify/%s/%s/%s/%s' title='Mark Attendance'>Mark Attendance</a>",
                $classLink,
                $this->getEvent()->getStartTime()->getDatabaseDate(),
                implode(',', $this->getEvent()->getAttendancePeriods()),
                $this->getEvent()->getTimetableCode()
            );
        } else {
            $hasListButton = false;
        }


        /* Congeal all that stuff
         */
        $start = $this->getEvent()->getStartTime()->getDatabaseDate();
        $end = $this->getEvent()->getEndTime()->getDatabaseDate();
        $all_day = $this->getEvent()->getAllday();
        if (!$all_day) {
            $start .= ' ' . $this->getEvent()->getStartTime()->getDatabaseTime();
            $end .= ' ' . $this->getEvent()->getEndTime()->getDatabaseTime();
        } else {
            // FullCalendar (and vCal) 2.0 treats event ends as exclusive.
            // Doesn't really matter for timed events, but all day events
            // get truncated by one day.
            $end = $this->getEvent()->getEndTime()->addDays(1)->getDatabaseDate();
        }

        //
        // Add campus list to list of classes
        $classes = $this->getEvent()->getEventClasses();

        $event = array(
            'resourceId'=> $this->getEvent()->getResourceId(),
            'title'     => $this->getEvent()->getTitle(),
            'start'     => $start,
            'end'       => $end,

            /* Whether you can edit this event or not
             */
            'editable'  => false,

            /* Whether it's an all-day event or not
             * If allDay is true, then you can't resize it
             */
            'allDay'    => $all_day,

            /* Colour of the event
             */
            'color'     => $this->getEvent()->getColour(),

            /* Campus slug so you can filter events
             */
            'className' => $classes,

            /* Extra data that the Event Object doesn't directly support
             */
            'data'      => array(
                'meta'       => array(
                    'eventId'     => $this->getEvent()->getEventId(),
                    'time'        => $this->getEvent()->getTime(),
                    'detail'      => \Scrub::html($this->getEvent()->getDetail()),
                    'location'    => $this->getEvent()->getLocation(),
                    'eventTypeId' => $this->getEvent()->getType(),
                    'eventType'   => 'type' . $this->getEvent()->getType(),
                    'type'        => $this->getEvent()->getEventType(),
                    'author'      => $this->getEvent()->getAuthorName(),
                    'authorId'    => $this->getEvent()->getAuthorId(),
                    'level'       => $this->getEvent()->getEventClasses(),
                    'completed'   => $this->getEvent()->getCompleted(),
                ),
                'links'      => array(
                    'category' => array(
                        'id'   => $this->getEvent()->getCategoryId(),
                        'name' => $this->getEvent()->getCategoryName(),
                    ),
                ),
                'styles'     => array(
                    'visible' => '',
                ),
                'attendance' => array(
                    'icon'          => $hasIcon,
                    'status'        => $hasStatus,
                    'listButton'    => $hasListButton,
                    'requestButton' => $hasAttendanceRequestButton,
                ),
            ),
        );

        return json_encode($event, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }


}
