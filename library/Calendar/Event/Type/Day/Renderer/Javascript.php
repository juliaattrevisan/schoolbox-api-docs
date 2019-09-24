<?php
/**
 * Renders out a JSON blob for day event objects, this is used by full calendar
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Day\Renderer;

class Javascript extends Base
{
    /**
     * @return string
     */
    public function render(): string
    {
        /* Future events are visible to superusers, event owners, group members with write access
         */
        if ($this->isTranslucent()) {
            $cssClass = 'translucent';
        } else {
            $cssClass = '';
        }

        /* Congeal all that stuff
         */
        $start   = $this->getEvent()->getStartTime()->getDatabaseDate();
        $end     = $this->getEvent()->getEndTime()->getDatabaseDate();
        $all_day = $this->getEvent()->getAllday();
        if (!$all_day) {
            $start .= ' ' . $this->getEvent()->getStartTime()->getDatabaseTime();
            $end   .= ' ' . $this->getEvent()->getEndTime()->getDatabaseTime();
        } else {
            // FullCalendar (and vCal) 2.0 treats event ends as exclusive.
            // Doesn't really matter for timed events, but all day events
            // get truncated by one day.
            $end = $this->getEvent()->getEndTime()->addDays(1)->getDatabaseDate();
        }

        //
        // Add campus list to list of classes
        $classes = $this->getEvent()->getEventClasses();

        $event = [
            'resourceId' => $this->getEvent()->getResourceId(),
            'title'      => $this->getEvent()->getTitle(),
            'start'      => $start,
            'end'        => $end,

            /* Whether you can edit this event or not
             */
            'editable'   => $this->getEvent()->isEditable(),

            /* Whether it's an all-day event or not
             * If allDay is true, then you can't resize it
             */
            'allDay'     => $all_day,

            /* Colour of the event
             */
            'color'      => $this->getEvent()->getColour(),

            /* Campus slug so you can filter events
             */
            'className'  => $classes,

            /* Extra data that the Event Object doesn't directly support
             */
            'data'       => [
                'meta'       => [
                    'editable'    => $this->getEvent()->isEditable(),
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
                    'seriesId'    => $this->getEvent()->getSeriesId(),
                ],
                'links'      => [
                    'category' => [
                        'id'   => $this->getEvent()->getCategoryId(),
                        'name' => $this->getEvent()->getCategoryName(),
                    ],
                ],
                'styles'     => [
                    'visible' => $cssClass,
                ],
                'attendance' => $this->renderEventAttendance(),
            ],
        ];

        return json_encode($event, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }


    /**
     * Renders vars for event attendance.
     *
     * @return array
     */
    private function renderEventAttendance(): array
    {
        $hasIcon                    = false;
        $hasStatus                  = false;
        $hasAttendanceRequestButton = false;
        $hasListButton              = false;
        $nextAttendanceAction       = '';
        if ($this->getEvent()->canAttend()) {
            $hasIcon = true;

            /* Anyone with write access can approve attendance
             */
            if ($this->getEvent()->isEditable()) {
                $hasListButton = true;
            }

            $hasStatus = $this->attendanceStatus();

            if ($hasStatus) {
                $hasAttendanceRequestButton = 'Unattend';
                $nextAttendanceAction       = 'remove';
            } else {
                $hasAttendanceRequestButton = 'RSVP';
                $nextAttendanceAction       = 'add';
            }
        }

        return [
            'icon'          => $hasIcon,
            'status'        => $hasStatus,
            'nextAction'    => $nextAttendanceAction,
            'requestButton' => $hasAttendanceRequestButton,
            'listButton'    => $hasListButton,
        ];
    }
}
