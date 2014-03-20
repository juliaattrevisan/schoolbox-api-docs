<?php
/**
 * Controller for resource bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;
use Alaress\Web\Controller\Base;
use Schoolbox\ResourceBooking\Item;
use EOTC\Calendar\EventMapper;

class Controller extends Base {

    /**
     * @var Mapper
     */
    protected $bookingMapper;

    /**
     * @var Item\Mapper
     */
    protected $resourceMapper;

    /**
     * @var EventMapper
     */
    protected $eventMapper;

    /**
     * Creates a booking for an item.
     */
    public function book($id) {
        /** @var Item\Model $resource */
        $resource = $this->getResourceMapper()->findById($id);
        $response = $this->getResponse();
        $response->content->set(
            array(
                'template' => '@resourceBooking/book.twig',
                'data'     => array(
                    'resource' => $resource,
                ),
            )
        );
    }

    /**
     * Outputs the calendar for this item.
     */
    public function bookingCalendar($id) {
        // Output a calendar as JSON
        // For testing, output the calendar for current user first.
        $calendar = new \Calendar_Api_EventType_Event_Schoolbox_Default(
            $this->getUser(), array()
        );
        /** @var \Calendar_Event_Type_Abstract[] $eventList */
        $eventList = $calendar->getEvents(
            $this->getRequest()->query['start'],
            $this->getRequest()->query['end']
        );
//        // Feed the beast
//        $renderer = new \Calendar_Event_Type_Day_Renderer_Javascript(new \SitePage(false));
//        foreach ($eventList as $event) {
//            $event->accept($renderer);
//        }
//        // Render the event list as JSON.
//        // TODO make Calendar_Event_Collection and Calendar_Event_Type_Abstract implement JsonSerializable
////        $renderer = new \Calendar_Event_Collection_Renderer($this->getApi(), $startSequence->getUnixTimestamp(), $endSequence->getUnixTimestamp(), $this->getPage());
////        $renderer->renderAll();

        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data'   => array(),
            )
        );
    }

    /**
     * @param  Mapper $bookingMapper
     * @return $this
     */
    public function setBookingMapper($bookingMapper) {
        $this->bookingMapper = $bookingMapper;
        return $this;
    }

    /**
     * @return Mapper
     */
    public function getBookingMapper() {
        return $this->bookingMapper;
    }

    /**
     * @param  EventMapper $eventMapper
     * @return $this
     */
    public function setEventMapper($eventMapper) {
        $this->eventMapper = $eventMapper;
        return $this;
    }

    /**
     * @return EventMapper
     */
    public function getEventMapper() {
        return $this->eventMapper;
    }

    /**
     * @param  Item\Mapper $resourceMapper
     * @return $this
     */
    public function setResourceMapper($resourceMapper) {
        $this->resourceMapper = $resourceMapper;
        return $this;
    }

    /**
     * @return Item\Mapper
     */
    public function getResourceMapper() {
        return $this->resourceMapper;
    }

}
