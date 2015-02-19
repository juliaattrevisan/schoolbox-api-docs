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

use Aura\Router\Router;
use Alaress\Authorizer\Authorizer;
use Schoolbox\ResourceBooking\Booking;

class Calendar_Event_Type_ResourceBooking_Renderer_Javascript extends Calendar_Event_Type_Renderer_Abstract
{

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var Authorizer
     */
    protected $authorizer;

    /**
     * Setup: injects global router into this object, so it can use it to look
     * up URLs relating to actions on an event.
     */
    protected function setup()
    {
        $this->router     = $this->getPage()->getDi()->get('router');
        $this->authorizer = $this->getPage()->getDi()->get('authorizer');
    }

    /**
     * Renders an event
     *
     * @param Calendar_Event_Type_ResourceBooking $currentEvent
     */
    public function visit($currentEvent)
    {
//        // Future events are visible to superusers, event owners, group members with write access
//        if ($currentEvent->getPublishDate()->getUnixTimestamp() >= time()) {
//            $cssClass = 'translucent';
//        }
//        else {
//            $cssClass = '';
//        }

        // Resource bookings are never all day events.
        $start = $currentEvent->getStartTime()->getDatabaseDate()
               . ' ' . $currentEvent->getStartTime()->getDatabaseTime();
        $end   = $currentEvent->getEndTime()->getDatabaseDate()
               . ' ' . $currentEvent->getEndTime()->getDatabaseTime();

        // Putting any class other than "personal", "group" or "type{whatever}"
        // prevents this object from rendering, so add classes later.
        $classes = array(
            $currentEvent->getDerivedEventLevel(),
        );
        if (($eventType = $currentEvent->getType()) > 0) {
            $classes[] = 'type' . $eventType;
        }
        if (($connectorId = $currentEvent->getConnectorId()) > 0) {
            $classes[] = 'source' . $connectorId;
        }

        // Bookings might have a campus one day, currently they do not.
//        $campus_list = array_filter(array_map('trim', explode(',', $currentEvent->getCampusList())));
//        foreach ($campus_list as &$campus) {
//            $campus = 'campus'.trim($campus);
//        }
//        unset($campus);
//        if (!empty($campus_list)) {
//            $classes[] = implode(' ', $campus_list);
//        }

        // Show booking butonyes I have permission to view
        $booking        = $currentEvent->getBooking();
        $bookingId      = $booking->getId();
        $bookingButtons = array();

        // Modify butonye
        $editUrl = $this->getRouteIfAuthorized(
            'resourceBooking.edit',
            array('id' => $bookingId, 'item' => $booking,)
        );
        if ($editUrl !== false) {
            $bookingButtons[] = array(
                'label' => 'Modify',
                'class' => 'modify booking-modify',
                'href'  => $editUrl,
            );
        }

        // Cancel butonye
        $cancelUrl = $this->getRouteIfAuthorized(
            'resourceBooking.cancel',
            array('id' => $bookingId, 'item' => $booking,)
        );
        if ($cancelUrl !== false) {
            $bookingButtons[] = array(
                'label' => 'Delete',
                'class' => 'delete booking-cancel',
                'href'  => $cancelUrl,
            );
        }

        // Approve butonye (if not already approved)
        $status = $currentEvent->getBooking()->getStatus()->getId();
        if ($status != Booking\Status\Model::STATUS_APPROVED) {
            $approveUrl = $this->getRouteIfAuthorized(
                'resourceBooking.approve',
                array('id' => $bookingId, 'item' => $booking,)
            );
            if ($approveUrl !== false) {
                $bookingButtons[] = array(
                    'label' => 'Approve',
                    'class' => 'tick booking-approve',
                    'href'  => $approveUrl,
                );
            }
        }

        // Rejected butonye (if not already rejected)
        if ($status != Booking\Status\Model::STATUS_REJECTED) {
            $rejectUrl = $this->getRouteIfAuthorized(
                'resourceBooking.reject',
                array('id' => $bookingId, 'item' => $booking,)
            );
            if ($rejectUrl !== false) {
                $bookingButtons[] = array(
                    'label' => 'Reject',
                    'class' => 'reply booking-reject',
                    'href'  => $rejectUrl,
                );
            }
        }

        // Set title from resource name, and code and booking name
        // if either are there.
        $title = $currentEvent->getBooking()->getResource()->getName();
        if ($currentEvent->getBooking()->getResource()->getRoomCode()) {
            $title .= ' ['.$currentEvent->getBooking()->getResource()->getRoomCode().']';
        }
        if ($currentEvent->getBooking()->getName()) {
            $title .= ': '.$currentEvent->getBooking()->getName();
        }
        $detail = $currentEvent->getDetail();
        $event  = array(
            'title' => $title,
            'start' => "$start",
            'end' => "$end",

            /* Whether you can edit this event or not
             */
            'editable' => false,

            /* Whether it's an all-day event or not
             * If allDay is true, then you can't resize it
             */
            'allDay' => false,

            /* Colour of the event
             */
            'color' => $currentEvent->getColour(),

            /* Campus slug so you can filter events
             */
            'className' => $classes,

            /* Extra data that the Event Object doesn't directly support
             */
            'data' => array(
                'meta' => array(
                    'eventId' => $currentEvent->getEventId(),
                    'time' => $currentEvent->getTime(),
                    'detail' => Scrub::html($detail),
                    'location' => $currentEvent->getLocation(),
                    'eventTypeId' => $currentEvent->getType(),
                    'eventType' => 'type' . $currentEvent->getType(),
                    'type' => $currentEvent->getEventType(),
                    'author' => $currentEvent->getAuthorName(),
                    'authorId' => $currentEvent->getAuthorId(),
                    'level' => $currentEvent->getDerivedEventLevel(),
                    'completed' => $currentEvent->getCompleted(),
                    'seriesId' => $currentEvent->getBooking()->getEvent()->getSeriesId(),
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
                        'id'   => $currentEvent->getBooking()->getResource()->getId(),
                        'name' => $currentEvent->getBooking()->getResource()->getName(),
                    ),
                    'status'  => $currentEvent->getBooking()->getStatus()->jsonSerialize(),
                    'buttons' => $bookingButtons,
                    'reviewer' => array(
                        'id'   => $currentEvent->getBooking()->getReviewer()->getId() ?: -1,
                        'name' => $currentEvent->getBooking()->getReviewer()->formatName(),
                    ),
                ),
            )
        );

        echo json_encode($event);
        echo ",";
    }

    /**
     * Helper function - gets whether access to a route is allowed.
     *
     * @param string $name
     * @param array  $params
     *
     * @return bool|string False if not authorized, a URL if it is.
     */
    public function getRouteIfAuthorized($name, array $params = array())
    {
        if ($this->authorizer->isPathAuthorizedForUser($name, $params, $_SESSION['user'])) {
            return $this->router->generate($name, $params);
        }
        return false;
    }
}
