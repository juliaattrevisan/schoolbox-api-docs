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
use EOTC\Calendar;

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
     * @var Calendar\EventMapper
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
     * Creates a booking for this resource.
     *
     * @param int $id The resource to create a booking for.
     */
    public function createBooking($id) {
        // Create the booking, which in turn adds an event to the calendar.
        // This action saves a connected event.
        /** @var Calendar\Event $event */
        $event = $this->getEventMapper()->createModel();
        $event->setOwner($this->getUser());
        $event->setAuthor($this->getUser());
        $event->setAccess(Calendar\EventMapper::ACCESS_PERSONAL);
        // Currently role and campus must be set to empty collections.
        $event->setRoleCollection(new \Schoolbox\Collection());
        $event->setCampusCollection(new \Schoolbox\Collection());
//        $event->setStartDateTime(new \Alaress_Date_Format($this->getRequest()->post['booking-from']));
//        $event->setEndDateTime(new \Alaress_Date_Format($this->getRequest()->post['booking-to']);
        $event->setStartDateTime(new \Alaress_Date_Format(new \DateTime('12am tomorrow')));
        $event->setEndDateTime(new \Alaress_Date_Format(new \DateTime('1am tomorrow')));
        $event->setDetail($this->getRequest()->post['notes']);
        $this->getEventMapper()->save($event);
        $booking = new Model();
        $booking
            ->setEvent($event)
            ->setResource($this->getResourceMapper()->findById($id))
            ->setStatus(Status\Model::STATUS_PENDING);
        ;
        $this->getBookingMapper()->save($booking);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceBooking.book', array('id' => $id))
        );
    }

    /**
     * Outputs the calendar for this item.
     */
    public function bookingCalendar($id) {
        // Output a calendar as JSON
        // For testing, output the calendar for current user first.
//        $calendar = new \Calendar_Api_EventType_Event_Schoolbox_Default(
//            $this->getUser(), array()
//        );
//        /** @var \Calendar_Event_Type_Abstract[] $eventList */
//        $eventList = $calendar->getEvents(
//            $this->getRequest()->query['start'],
//            $this->getRequest()->query['end']
//        );
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
     * @param  Calendar\EventMapper $eventMapper
     * @return $this
     */
    public function setEventMapper($eventMapper) {
        $this->eventMapper = $eventMapper;
        return $this;
    }

    /**
     * @return Calendar\EventMapper
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
