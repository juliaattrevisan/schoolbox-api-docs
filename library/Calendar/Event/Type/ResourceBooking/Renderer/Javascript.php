<?php
/**
 * Calendar_Event_Type_ResourceBooking_Renderer_Javascript
 *
 * Renders out a Calendar_Event_Type_ResourceBooking object.
 *
 * @author     Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright  Copyright 2019, Schoolbox Pty Ltd
 * @link       https://schoolbox.com.au/
 * @copiedFrom Steve Chan <steve.chan@alaress.com.au>
 */

namespace Calendar\Event\Type\ResourceBooking\Renderer;

use Schoolbox\ResourceBooking\Booking;
use Schoolbox\User;
use \Scrub;

class Javascript extends Base
{
    /**
     * Renders an event
     *
     */
    public function render(): string
    {
        // Resource bookings are never all day events.
        $start = $this->getEvent()->getStartTime()->getDatabase();
        $end = $this->getEvent()->getEndTime()->getDatabase();

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
        $booking = $this->getEvent()->getBooking();
        $bookingButtons = array();

        // Modify butonye
        $editUrl = $this->getEditURL();
        if ($editUrl !== false) {
            $bookingButtons[] = array(
                'label'  => 'Modify',
                'class'  => 'icon-modify',
                'href'   => $editUrl,
                'action' => 'modify',
            );
        }

        // Cancel butonye
        $cancelUrl = $this->getCancelURL();
        if ($cancelUrl !== false) {
            $bookingButtons[] = array(
                'label'  => 'Delete',
                'class'  => 'icon-delete',
                'href'   => $cancelUrl,
                'action' => 'cancel',
            );
        }

        // Approve butonye (if not already approved)
        $approveUrl = $this->getApproveURL();
        if ($approveUrl !== false) {
            $bookingButtons[] = array(
                'label'  => 'Approve',
                'class'  => 'icon-tick',
                'href'   => $approveUrl,
                'action' => 'approve',
            );
        }

        // Rejected butonye (if not already rejected)
        $rejectUrl = $this->getRejectURL();
        if ($rejectUrl !== false) {
            $bookingButtons[] = array(
                'label'  => 'Reject',
                'class'  => 'icon-close',
                'href'   => $rejectUrl,
                'action' => 'reject',
            );
        }

        // Related booking display
        $relatedBookings = [];
        foreach ($booking->getRelatedBookingList() as $relatedBooking) {
            $reviewHref = $this->getRouteIfAuthorized(
                'resourceItem.read',
                [
                    'id' => $relatedBooking->getResource()->getId(),
                    'format' => ''
                ]
            );
            if ($reviewHref) {
                $reviewHref .= '?date=' . urlencode($relatedBooking->getEvent()->getStartDateTime()->getURLDate());
            }
            $relatedBookings[] = [
                'title'  => $relatedBooking->getResource()->getName(),
                'item'   => array(
                    'id'   => $relatedBooking->getResource()->getId(),
                    'name' => $relatedBooking->getResource()->getName(),
                ),
                'status' => $relatedBooking->getStatus()->jsonSerialize(),
                'href'   => $reviewHref,
            ];
        }

        // Event may or may not have a reviewer.
        $reviewer = $this->getEvent()->getBooking()->getReviewer();
        if ($reviewer instanceof User) {
            $reviewerRender = [
                'id'   => $reviewer->getId(),
                'name' => $reviewer->formatName(),
            ];
        } else {
            $reviewerRender = [
                'id'   => -1,
                'name' => '',
            ];
        }

        // Set title from resource name, and code and booking name
        // if either are there.

        $detail = $this->getEvent()->getDetail();
        $event = array(
            'resourceId' => $booking->getResource()->getId(),
            'title'      => $this->getEvent()->getTitle(),
            'start'      => $start,
            'end'        => $end,

            /* Whether you can edit this event or not
             */
            'editable'   => false,

            /* Whether it's an all-day event or not
             * If allDay is true, then you can't resize it
             */
            'allDay'     => false,

            /* Colour of the event
             */
            'color'      => $this->getEvent()->getColour(),

            /* Campus slug so you can filter events
             */
            'className'  => $classes,

            /* Extra data that the Event Object doesn't directly support
             */
            'data'       => array(
                'meta'       => array(
                    'eventId'     => $this->getEvent()->getEventId(),
                    'time'        => $this->getEvent()->getTime(),
                    'detail'      => Scrub::html($detail),
                    'location'    => $this->getEvent()->getLocation(),
                    'eventTypeId' => $this->getEvent()->getType(),
                    'eventType'   => 'type' . $this->getEvent()->getType(),
                    'type'        => $this->getEvent()->getEventType(),
                    'author'      => $this->getEvent()->getAuthorName(),
                    'authorId'    => $this->getEvent()->getAuthorId(),
                    'level'       => $this->getEvent()->getEventClasses(),
                    'completed'   => $this->getEvent()->getCompleted(),
                    'seriesId'    => $this->getEvent()->getBooking()->getEvent()->getSeriesId(),
                ),
                'links'      => array(),
                'styles'     => array(
                    'visible' => '',
                ),
                'attendance' => array(
                    'icon'          => false,
                    'status'        => false,
                    'requestButton' => false,
                    'listButton'    => false,
                ),
                'booking'    => array(
                    'item'     => array(
                        'id'   => $this->getEvent()->getBooking()->getResource()->getId(),
                        'name' => $this->getEvent()->getBooking()->getResource()->getName(),
                    ),
                    'status'   => $this->getEvent()->getBooking()->getStatus()->jsonSerialize(),
                    'buttons'  => $bookingButtons,
                    'reviewer' => $reviewerRender,
                    'related'  => $relatedBookings,
                ),
            ),
        );

        return json_encode($event, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
