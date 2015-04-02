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

use Calendar\Event\Type\RendererBase;
use Aura\Router\Router;
use Alaress\Authorizer\Authorizer;
use Schoolbox\ResourceBooking\Booking;

class Javascript extends RendererBase
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
     * @param Calendar_Event_Type_ResourceBooking $this->getEvent()
     */
    public function render()
    {
//        // Future events are visible to superusers, event owners, group members with write access
//        if ($this->getEvent()->getPublishDate()->getUnixTimestamp() >= time()) {
//            $cssClass = 'translucent';
//        }
//        else {
//            $cssClass = '';
//        }

        // Resource bookings are never all day events.
        $start = $this->getEvent()->getStartTime()->getDatabaseDate()
               . ' ' . $this->getEvent()->getStartTime()->getDatabaseTime();
        $end   = $this->getEvent()->getEndTime()->getDatabaseDate()
               . ' ' . $this->getEvent()->getEndTime()->getDatabaseTime();

        // Putting any class other than "personal", "group" or "type{whatever}"
        // prevents this object from rendering, so add classes later.
        $classes = array(
            $this->getEvent()->getDerivedEventLevel(),
        );
        if (($eventType = $this->getEvent()->getType()) > 0) {
            $classes[] = 'type' . $eventType;
        }
        if (($connectorId = $this->getEvent()->getConnectorId()) > 0) {
            $classes[] = 'source' . $connectorId;
        }

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
        $booking        = $this->getEvent()->getBooking();
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
        $status = $this->getEvent()->getBooking()->getStatus()->getId();
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
        $title = $this->getEvent()->getBooking()->getResource()->getName();
        if ($this->getEvent()->getBooking()->getResource()->getRoomCode()) {
            $title .= ' ['.$this->getEvent()->getBooking()->getResource()->getRoomCode().']';
        }
        if ($this->getEvent()->getBooking()->getName()) {
            $title .= ': '.$this->getEvent()->getBooking()->getName();
        }
        $detail = $this->getEvent()->getDetail();
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
                    'location' => $this->getEvent()->getLocation(),
                    'eventTypeId' => $this->getEvent()->getType(),
                    'eventType' => 'type' . $this->getEvent()->getType(),
                    'type' => $this->getEvent()->getEventType(),
                    'author' => $this->getEvent()->getAuthorName(),
                    'authorId' => $this->getEvent()->getAuthorId(),
                    'level' => $this->getEvent()->getDerivedEventLevel(),
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
                ),
            )
        );

        return json_encode($event).',';
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
