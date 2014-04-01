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
        if (($startDate = $this->getRequest()->query['startDate'])) {
            // Convert format of startDate and startTime
            if ($startDate = DateTime::createFromFormat('Y-m-d', $startDate)) {
                // Was a time set? If so, add that time to the start date.
                // Otherwise, default to start of next hour.
                if (($time = $this->getRequest()->query['startTime'])
                    && ($time = DateTime::createFromFormat('H:i', $time))
                ) {
                    $startDate->setTime($time->format('H'), $time->format('i'));
                }
                else {
                    $startDate->modify('+1 hour');
                    $startDate->setTime($startDate->format('H'), 0);
                }

                // Set the start and end date and time
                $item->getEvent()->setStartDate($startDate->format('d/m/Y'));
                $item->getEvent()->setStartTime($startDate->format('H:i'));
                $item->getEvent()->setStartDateTime($startDate);

                $endDate = new DateTime();
                $endDate->setTimestamp($startDate->getTimestamp() + $resource->getMinReservationTime()->getSecondTotal());
                $item->getEvent()->setEndDate($endDate->format('d/m/Y'));
                $item->getEvent()->setEndTime($endDate->format('H:i'));
                $item->getEvent()->setEndDateTime($endDate);
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
        $booking  = $this->createBookingFromPostData($resource);

        // Create a related booking for any related items that were chosen.
        $relatedSelections = $this->getRequest()->post['related_item'] ?: array();
        if (!empty($relatedSelections)) {
            foreach ($resource->getRelatedResourceList() as $relatedResource) {
                if (in_array($relatedResource->getId(), $relatedSelections)) {
                    $relatedBooking = $this->createBookingFromPostData($relatedResource);
                    $booking->addRelatedBooking($relatedBooking);
                }
            }
            if (count($booking->getRelatedBookingList())) {
                $this->getBookingMapper()->saveRelatedBookings($booking);
            }
        }

        // and redirect somewhere.
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('resourceItem.read', array('id' => $id))
        );
    }

    /**
     * Helper for creating a booking out of a resource item and post data.
     *
     * @param  Item\Model $resource
     * @return Model      The model that was created.
     */
    protected function createBookingFromPostData(Item\Model $resource) {
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
        $startDate = new Alaress_Date_Format($this->getRequest()->post['start_datetime']['date'], 'd/m/y');
        $endDate   = new Alaress_Date_Format($this->getRequest()->post['end_datetime']['date'], 'd/m/y');
        $event->setStartDate($startDate->getDatabaseDate());
        $event->setEndDate($endDate->getDatabaseDate());

        // Convert time inputs to datetimes.
        $startTime = DateTime::createFromFormat(
            'g:iA',
            sprintf(
                '%02d:%02d%s',
                $this->getRequest()->post['start_datetime']['hour'],
                $this->getRequest()->post['start_datetime']['minute'],
                $this->getRequest()->post['start_datetime']['meridien']
            )
        );
        if ($startTime instanceof DateTime) {
            $event->setStartTime($startTime->format('H:i'));
        }
        $endTime = DateTime::createFromFormat(
            'g:iA',
            sprintf(
                '%02d:%02d%s',
                $this->getRequest()->post['end_datetime']['hour'],
                $this->getRequest()->post['end_datetime']['minute'],
                $this->getRequest()->post['end_datetime']['meridien']
            )
        );
        if ($endTime instanceof DateTime) {
            $event->setEndTime($endTime->format('H:i'));
        }

        $event->setPublishDateTime(new Alaress_Date_Format(new DateTime()));

        $event->setNote("Booking: ".$resource->getName());
        $event->setDetail($this->getRequest()->post['notes']);
        $this->getEventMapper()->save($event);

        // And create a new booking with the event attached.
        $booking = new Model();
        $booking
            ->setEvent($event)
            ->setResource($resource)
            ->setStatus(Status\Model::STATUS_PENDING);
        ;
        // What about the resource? If it's an open resource, confirm the
        // booking immediately.
        if ($resource->getModerationType()->getId() == Item\ModerationType\Model::TYPE_OPEN) {
            $booking->setStatus(Status\Model::STATUS_APPROVED);
        }
        $this->getBookingMapper()->save($booking);
        return $booking;
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
            $editUrl = $this->getRouteIfAuthorized('resourceBooking.edit', array('id' => $booking->getId()));
            if ($editUrl !== false) {
                $buttons[] = array(
                    'label' => 'Modify',
                    'class' => 'modify',
                    'href'  => $this->getRouter()->generate(
                        'resourceBooking.edit',
                        array('id' => $booking->getId())
                    ),
                );
            }

            switch ($booking->getStatus()->getId()) {
                case Status\Model::STATUS_PENDING:
                    $class = 'pending';
                    // Is approve/reject okay for this item?
                    $approveUrl = $this->getRouteIfAuthorized(
                        'resourceBooking.approve',
                        array('id' => $booking->getId())
                    );
                    if ($approveUrl !== false) {
                        $buttons[] = array(
                            'label' => 'Approve',
                            'class' => 'tick',
                            'href'  => $approveUrl,
                        );
                    }
                    $rejectUrl = $this->getRouteIfAuthorized(
                        'resourceBooking.reject',
                        array('id' => $booking->getId())
                    );
                    if ($rejectUrl !== false) {
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
