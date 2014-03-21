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
        /** @var Item\Model $resource */
        $resource = $this->getResourceMapper()->findById($id);
        // Create the booking, which in turn adds an event to the calendar.
        // This action saves a connected event.
        /** @var Calendar\Event $event */
        $event = $this->getEventMapper()->createModel();
        // Whatever the booking type is.
        // Could probably be left unset so events show on a different event
        // source.
        // Possible Sources:
        // * My bookings (shows in my elements)
        // * Bookings for items I moderate?)
        $event->setType(1);
        $event->setOwner($this->getUser());
        $event->setAuthor($this->getUser());
        $event->setSid($this->getUser()->getId());
        $event->setAccess(Calendar\EventMapper::ACCESS_PERSONAL);
        // Currently role and campus must be set to empty collections.
        $event->setRoleCollection(new \Schoolbox\Collection());
        $event->setCampusCollection(new \Schoolbox\Collection());
//        $event->setStartDateTime(new \Alaress_Date_Format($this->getRequest()->post['booking-from']));
//        $event->setEndDateTime(new \Alaress_Date_Format($this->getRequest()->post['booking-to']);
        // Currently I can't setStartDateTime() and have that propogate to
        // startDate and startTime.
        $date = new \DateTime('tomorrow');
        $event->setStartDate($date->format('Y-m-d'));
        $event->setStartTime('00:00:00');
        $event->setEndDate($date->format('Y-m-d'));
        $event->setEndTime('01:00:00');
        $event->setPublishDateTime(new \Alaress_Date_Format(new \DateTime()));
        $event->setNote("Booking for ".$resource->getName());
        $event->setDetail($this->getRequest()->post['notes']);
        $this->getEventMapper()->save($event);
        $booking = new Model();
        $booking
            ->setEvent($event)
            ->setResource($resource)
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
        // the find method should receive:
        // Datetime objects?
        /** @var Model[] $bookings */
        $bookings = $this->getBookingMapper()->findForResource(
            $id,
            $this->getRequest()->query['start'],
            $this->getRequest()->query['end']
        );

        // Pass bookings through a data marshaler - converts bookings to a JSON
        // format which FullCalendar understands.
        $output = array();
        foreach ($bookings as $booking) {
            $currentEvent = $booking->getEvent();
            $event = array(
                'title' => $currentEvent->getNote(),
                'start' => $currentEvent->getStartDate().' '.$currentEvent->getStartTime(),
                'end'   => $currentEvent->getEndDate().' '.$currentEvent->getEndTime(),

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

//                /* Campus slug so you can filter events
//                 */
//                'className' => $classes,

                /* Extra data that the Event Object doesn't directly support
                 */
                'data' => array(
                    'meta' => array(
                        'eventId' => $currentEvent->getId(),
//                        'time' => $currentEvent->getTime(),
                        'detail' => ($currentEvent->getDetail()),
                        'location' => ($currentEvent->getLocation()),
                        'eventTypeId' => $currentEvent->getType(),
                        'eventType' => 'type' . $currentEvent->getType(),
                        'type' => $currentEvent->getEventType(),
                        'author' => ($currentEvent->getAuthor()->formatName()),
                        'authorId' => $currentEvent->getAuthor()->getId(),
//                        'level' => $currentEvent->getDerivedEventLevel(),
//                        'completed' => $currentEvent->getCompleted(),
                    ),
//                    'links' => array(
//                        'category' => array(
//                            'id' => $categoryId,
//                            'name' => $categoryName,
//                        )
//                    ),
//                    'styles' => array(
//                        'visible' => $cssClass,
//                    ),
//                    'attendance' => array(
//                        'icon' => $hasIcon,
//                        'status' => $hasStatus,
//                        'requestButton' => $hasAttendanceRequestButton,
//                        'listButton' => $hasListButton,
//                    )
                )
            );
            $output[] = $event;
        }

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
                'data'   => $output,
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
