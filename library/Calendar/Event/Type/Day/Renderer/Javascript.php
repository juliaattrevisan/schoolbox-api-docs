<?php

/**
 * Renders out a JSON blob for day event objects, this is used by full calendar
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Day\Renderer;

use Alaress_Date_Format;
use Calendar\Event\Type\Day\Event;

class Javascript extends Base
{
    /**
     * @return string
     */
    public function render(): string
    {
        /* Future events are visible to superusers, event owners, group members with write access
         */
        if (!$this->getEvent()->isPublishedAtDate(Alaress_Date_Format::now())) {
            $cssClass = 'translucent';
        } else {
            $cssClass = '';
        }

        /* Congeal all that stuff
         */
        $all_day = $this->getEvent()->getAllday();
        if (!$all_day) {
            $start = $this->getEvent()->getStartTimeForCalendar();
            $end   = $this->getEvent()->getEndTimeForCalendar();
        } else {
            $start   = $this->getEvent()->getStartTime()->getDatabaseDate();
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

            /* Whether you can edit this event or not.
             * Note this also determines whether the calendar item is draggable.
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
                    'variant'     => $this->getEvent()->getEventVariant(),
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
                    'path'     => $this->getEvent()->getPath(),
                ],
                'styles'     => [
                    'visible' => $this->getEvent()->getActive() ? $cssClass : 'strike',
                ],
                'attendance' => $this->renderEventAttendance(),
                'bookings'    => $this->renderBookings($this->getEvent()),
                'socsMusic'  => [
                    'staff'    => $this->getEvent()->getSocsMusicStaff(),
                    'students' => $this->getEvent()->getSocsMusicStudents(),
                ],
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
        $currentStatus = null;
        $hasAttendance = (bool)$this->getEvent()->canAttend();
        if ($hasAttendance) {
            $currentStatus = $this->attendanceStatus();
        }
        return [
            'hasAttendance' => $hasAttendance,
            'status'        => [
                'current' => $currentStatus,
            ],
        ];
    }

    /**
     * Render all the bookings linked to the given event, in the array representation expected by view-event-modal.vue.
     *
     * @param Event $event
     * @return array
     */
    private function renderBookings(Event $event): array
    {
        $bookingCollection = $event->getBookingCollection();
        if ($bookingCollection === null) {
            return [];
        }

        $bookingsArray = [];
        foreach ($bookingCollection as $booking) {
            $href = $this->getRouteIfAuthorized(
                'resourceItem.read',
                [
                    'id' => $booking->getResource()->getId(),
                    'format' => '',
                ]
            );
            if ($href) {
                $href .= '?date=' . urlencode($event->getStartTime()->getURLDate());
            }
            $bookingsArray[] = [
                'id' => $booking->getId(),
                'item' => [
                    'id' => $booking->getResource()->getId(),
                    'name' => $booking->getResource()->getName(),
                ],
                'status' => $booking->getStatus()->jsonSerialize(),
                'href' => $href,
            ];
        }

        return $bookingsArray;
    }
}
