<?php
/**
 * Calendar_Event_Type_ResourceBooking_Renderer_Javascript
 *
 * Renders out a Calendar_Event_Type_ResourceBooking object.
 *
 * @author     Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright  Copyright 2014, Alaress Pty Ltd
 * @link       http://www.alaress.com.au/
 * @copiedFrom Steve Chan <steve.chan@alaress.com.au>
 */

namespace Calendar\Event\Type\ResourceBooking\Renderer;

use Calendar\Event\Type\ResourceBooking\Renderer\Base;
use Schoolbox\ResourceBooking\Booking;
use \Scrub;

class Javascript extends Base
{
    /**
     * Renders an event
     *
     */
    public function render()
    {
        // Resource bookings are never all day events.
        $start = $this->getEvent()->getStartTime()->getDatabase();
        $end   = $this->getEvent()->getEndTime()->getDatabase();

        // Putting any class other than "personal", "group" or "type{whatever}"
        // prevents this object from rendering, so add classes later.
        $classes = $this->getEvent()->getEventClasses();

        // Bookings might have a campus one day, currently they do not.
//        $campus_list = array_filter(array_map('trim', explode(',', $this->getEvent()->getCampusList())));
//        foreach ($campus_list as &$campus) {
//            $campus = 'campus'.trim($campus);
//        }
//        unset($campus);
//        if (!empty($campus_list)) {
//            $classes[] = implode(' ', $campus_list);
//        }

        // Show booking butonyes I have permission to view
        /** @var Booking\Model $booking */
        $booking        = $this->getEvent()->getBooking();
        $bookingButtons = array();

        // Modify butonye
        $editUrl = $this->getEditURL();
        if ($editUrl !== false) {
            $bookingButtons[] = array(
                'label' => 'Modify',
                'class' => 'modify booking-modify',
                'href'  => $editUrl,
            );
        }

        // Cancel butonye
        $cancelUrl = $this->getCancelURL();
        if ($cancelUrl !== false) {
            $bookingButtons[] = array(
                'label' => 'Delete',
                'class' => 'delete booking-cancel',
                'href'  => $cancelUrl,
            );
        }

        // Approve butonye (if not already approved)
        $approveUrl = $this->getApproveURL();
        if ($approveUrl !== false) {
            $bookingButtons[] = array(
                'label' => 'Approve',
                'class' => 'tick booking-approve',
                'href'  => $approveUrl,
            );
        }

        // Rejected butonye (if not already rejected)
        $rejectUrl = $this->getRejectURL();
        if ($rejectUrl !== false) {
            $bookingButtons[] = array(
                'label' => 'Reject',
                'class' => 'reply booking-reject',
                'href'  => $rejectUrl,
            );
        }

        // Related booking display
        $relatedBookings = [];
        foreach ($booking->getRelatedBookingList() as $relatedBooking) {
            $reviewHref = $this->getRouteIfAuthorized(
                'resourceItem.read',
                ['id' => $relatedBooking->getResource()->getId()]
            );
            if ($reviewHref) {
                $reviewHref .= '?date='.urlencode($relatedBooking->getEvent()->getStartDateTime()->getURLDate());
            }
            $relatedBookings[] = [
                'title' => $relatedBooking->getResource()->getName(),
                'item'    => array(
                    'id'   => $relatedBooking->getResource()->getId(),
                    'name' => $relatedBooking->getResource()->getName(),
                ),
                'status'  => $relatedBooking->getStatus()->jsonSerialize(),
                'href'    => $reviewHref,
            ];
        }

        // Set title from resource name, and code and booking name
        // if either are there.

        $detail = $this->getEvent()->getDetail();
        $event  = array(
            'title' => $this->getEvent()->getTitle(),
            'start' => $start,
            'end' => $end,

            /* Whether you can edit this event or not
             */
            'editable' => false,

            /* Whether it's an all-day event or not
             * If allDay is true, then you can't resize it
             */
            'allDay' => false,

            /* Colour of the event
             */
            'color' => $this->getEvent()->getColour(),

            /* Campus slug so you can filter events
             */
            'className' => $classes,

            /* Extra data that the Event Object doesn't directly support
             */
            'data' => array(
                'meta' => array(
                    'eventId' => $this->getEvent()->getEventId(),
                    'time' => $this->getEvent()->getTime(),
                    'detail' => Scrub::html($detail),
                    'location' =>  $this->getEvent()->getCategory(),
                    'eventTypeId' => $this->getEvent()->getType(),
                    'eventType' => 'type' . $this->getEvent()->getType(),
                    'type' => $this->getEvent()->getEventType(),
                    'author' => $this->getEvent()->getAuthorName(),
                    'authorId' => $this->getEvent()->getAuthorId(),
                    'level' => $this->getEvent()->getEventClasses(),
                    'completed' => $this->getEvent()->getCompleted(),
                    'seriesId' => $this->getEvent()->getBooking()->getEvent()->getSeriesId(),
                ),
                'links' => array(),
                'styles' => array(
                    'visible' => '',
                ),
                'attendance' => array(
                    'icon' => false,
                    'status' => false,
                    'requestButton' => false,
                    'listButton' => false,
                ),
                'booking' => array(
                    'item'    => array(
                        'id'   => $this->getEvent()->getBooking()->getResource()->getId(),
                        'name' => $this->getEvent()->getBooking()->getResource()->getName(),
                    ),
                    'status'  => $this->getEvent()->getBooking()->getStatus()->jsonSerialize(),
                    'buttons' => $bookingButtons,
                    'reviewer' => array(
                        'id'   => $this->getEvent()->getBooking()->getReviewer()->getId() ?: -1,
                        'name' => $this->getEvent()->getBooking()->getReviewer()->formatName(),
                    ),
                    'related' => $relatedBookings,
                ),
            )
        );

        return json_encode($event).',';
    }
}
