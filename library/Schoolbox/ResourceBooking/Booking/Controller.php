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
use DateTime;
use Alaress_Date_Format;
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
        /** @var Model $item */
        /** @var Item\Model $resource */
        $resource = $this->getResourceMapper()->findById($id);
        $item     = $this->getBookingMapper()->createModel();
        $item
            ->setResource($resource)
            ->setEvent(new Calendar\Event());
        // Was the time set here? If so set it into the item.
        if (($date = $this->getRequest()->query['startDate'])) {
            // Convert format of startDate and startTime
            if ($date = DateTime::createFromFormat('Y-m-d', $date)) {
                $item->getEvent()->setStartDate($date->format('d/m/Y'));
            }
        }
        if (($time = $this->getRequest()->query['startTime'])) {
            // Time: Only hours and minutes
            if ($time = DateTime::createFromFormat('H:i', $time)) {
                $item->getEvent()->setStartTime($time->format('H:i'));
            }
        }
        $this->getResponse()->content->set(
            array(
                'template' => '@resourceBooking/book.twig',
                'data'     => array(
                    'item' => $item,
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
        // Event doesn't currently take Alaress_Date_Format objects either.
        $startDate = new Alaress_Date_Format($this->getRequest()->post['start-date'], 'd/m/y');
        $endDate   = new Alaress_Date_Format($this->getRequest()->post['end-date'], 'd/m/y');
        $event->setStartDate($startDate->getDatabaseDate());
        $event->setStartTime($this->getRequest()->post['start-time']);
        $event->setEndDate($endDate->getDatabaseDate());
        $event->setEndTime($this->getRequest()->post['end-time']);
        $event->setPublishDateTime(new Alaress_Date_Format(new DateTime()));

        $event->setNote("Booking: ".$resource->getName());
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
            $this->getRequest()->query['end'],
            $this->getRequest()->query['status']
        );

        // Pass bookings through a data marshaler - converts bookings to a JSON
        // format which FullCalendar understands.
        $output = array();
        foreach ($bookings as $booking) {
            $currentEvent = $booking->getEvent();

            // Buttons: depend on whether user is moderator.
            // Actions: approve/reject, view bookings for item
            $buttons = array();
            $class   = '';

            // Modify URL
            $modifyUrl = $this->getRouter()->generate(
                'resourceBooking.edit',
                array('id' => $booking->getId())
            );
            if ($this->getAuthorizer()->isPathAuthorizedForUser($modifyUrl)) {
                $buttons[] = array(
                    'label' => 'Modify',
                    'class' => 'modify',
                    'href'  => $modifyUrl,
                );
            }

            switch ($booking->getStatus()->getId()) {
                case Status\Model::STATUS_PENDING:
                    $class = 'pending';
                    // Is approve/reject okay for this item?
                    $approveUrl = $this->getRouter()->generate(
                        'resourceBooking.approve',
                        array('id' => $booking->getId())
                    );
                    if ($this->getAuthorizer()->isPathAuthorizedForUser($approveUrl)) {
                        $buttons[] = array(
                            'label' => 'Approve',
                            'class' => 'tick',
                            'href'  => $approveUrl,
                        );
                    }
                    $rejectUrl = $this->getRouter()->generate(
                        'resourceBooking.reject',
                        array('id' => $booking->getId())
                    );
                    if ($this->getAuthorizer()->isPathAuthorizedForUser($rejectUrl)) {
                        $buttons[] = array(
                            'label' => 'Reject',
                            'class' => 'delete',
                            'href'  => $rejectUrl,
                        );
                    }
                    break;

                case Status\Model::STATUS_APPROVED:
                    $class = 'approved';
                    break;


                case Status\Model::STATUS_REJECTED:
                    $class = 'rejected';
                    break;

            }

            $event = array(
                'title'     => $currentEvent->getNote(),
                'start'     => $currentEvent->getStartDate().' '.$currentEvent->getStartTime(),
                'end'       => $currentEvent->getEndDate().' '.$currentEvent->getEndTime(),
                'editable'  => true,
                'allDay'    => false,
                'color'     => $currentEvent->getColour(),
                // Styles: should vary depending on booking
                'className' => "$class booking",
                'data' => array(
                    'meta' => array(
                        'eventId'     => $currentEvent->getId(),
                        'detail'      => $currentEvent->getDetail(),
                        'location'    => $currentEvent->getLocation(),
                        'eventTypeId' => $currentEvent->getType(),
                        'type'        => $currentEvent->getEventType(),
                        'author'      => $currentEvent->getAuthor()->formatName(),
                        'authorId'    => $currentEvent->getAuthor()->getId(),
                    ),
                    'booking' => array(
                        'status'  => $booking->getStatus(),
                        'buttons' => $buttons,
                    )
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
     * Stub for edit action.
     *
     * @param int $id
     */
    public function edit($id) {
        $booking = $this->getBookingMapper()->findById($id);
    }

    /**
     * Marks a booking as approved.
     *
     * @param int $id The ID of the booking to approve.
     */
    public function approve($id) {
        $booking = $this->getBookingMapper()->findById($id);
        $booking->setStatus(Status\Model::STATUS_APPROVED);
        $this->getBookingMapper()->save($booking);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceBooking.book', array('id' => $id))
        );
        // HTML: redirect somewhere, JSON: output a response.
        // Showing HTML validation errors: use session flash variables to store.
//        $this->buildFormResponse(
//            array(
//                'location' => $this->getRouter()->generate('resourceBooking.book', array('id' => $id)),
//                'errors'   => array(),
//            )
//        );
    }

    /**
     * Marks a booking as rejected.
     *
     * @param int $id The ID of the booking to reject.
     */
    public function reject($id) {
        $booking = $this->getBookingMapper()->findById($id);
        $booking->setStatus(Status\Model::STATUS_REJECTED);
        $this->getBookingMapper()->save($booking);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceBooking.book', array('id' => $id))
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
