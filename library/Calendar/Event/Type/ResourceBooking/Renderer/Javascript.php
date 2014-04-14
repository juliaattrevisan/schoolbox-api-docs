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

class Calendar_Event_Type_ResourceBooking_Renderer_Javascript extends Calendar_Event_Type_Renderer_Abstract {

    /**
     * @var Router
     */
    protected $router;

    /**
     * Setup: injects global router into this object, so it can use it to look
     * up URLs relating to actions on an event.
     */
    protected function setup() {
        global $di;
        $this->router = $di->get('router');
    }

    /**
     * Renders an event
     *
     * @param Calendar_Event_Type_ResourceBooking $currentEvent
     */
    public function visit($currentEvent) {
        if ($this->isVisible($currentEvent)) {

            /* Future events are visible to superusers, event owners, group members with write access
             */
            if ($currentEvent->getPublishDate()->getUnixTimestamp() >= time()) {
                $cssClass = 'translucent';
            }
            else {
                $cssClass = '';
            }

            /* Congeal all that stuff
             * Resource bookings are never all day events.
             */
            $start = $currentEvent->getStartTime()->getDatabaseDate()
                   . ' ' . $currentEvent->getStartTime()->getDatabaseTime();
            $end   = $currentEvent->getEndTime()->getDatabaseDate()
                   . ' ' . $currentEvent->getEndTime()->getDatabaseTime();

            //
            // Add campus list to list of classes
            $classes = array(
                $currentEvent->getDerivedEventLevel(),
                'type' . $currentEvent->getType()
            );
            $campus_list = array_filter(array_map('trim', explode(',', $currentEvent->getCampusList())));
            foreach ($campus_list as &$campus) {
                $campus = 'campus'.trim($campus);
            }
            unset($campus);
            if (!empty($campus_list)) {
                $classes[] = implode(' ', $campus_list);
            }

            // If I'm the owner of this event, show view/cancel
            // If I'm a moderator, show confirm/reject.
            // (Use the booking controller to generate this instead, because it
            //  has access to the URL generators)

            // If I'm viewing this, then I must be able to cancel and modify.
            $bookingId      = $currentEvent->getBooking()->getId();
            $bookingButtons = array(
                array(
                    'class' => "modify booking-modify",
                    'href'  => $this->router->generate('resourceBooking.edit', array('id' => $bookingId)),
                    'label' => "modify",
                ),
                array(
                    'class' => "delete booking-cancel",
                    'href'  => $this->router->generate('resourceBooking.cancel', array('id' => $bookingId)),
                    'label' => "Cancel",
                ),
            );

            // Is someone other than the owner of the event viewing this?
            // If so, the viewing user must be a moderator, so show the moderate
            // options.
            if (!($currentEvent->getBooking()->getEvent()->getOwner()->getId() == $_SESSION['user']->getId())) {
                if (!$currentEvent->getBooking()->getStatus()->isApproved()) {
                    $bookingButtons[] = array(
                        'class' => 'tick booking-approve',
                        'href'  => $this->router->generate('resourceBooking.approve', array('id' => $bookingId)),
                        'label' => "Approve",
                    );
                }
                if (!$currentEvent->getBooking()->getStatus()->isRejected()) {
                    $bookingButtons[] = array(
                        'class' => 'reply booking-reject',
                        'href'  => $this->router->generate('resourceBooking.reject', array('id' => $bookingId)),
                        'label' => "Reject",
                    );
                }
            }

            $event = array(
                'title' => Scrub::text($currentEvent->getNote()),
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
                        'detail' => Scrub::html($currentEvent->getDetail()),
                        'location' => Scrub::text($currentEvent->getLocation()),
                        'eventTypeId' => $currentEvent->getType(),
                        'eventType' => 'type' . $currentEvent->getType(),
                        'type' => $currentEvent->getEventType(),
                        'author' => Scrub::text($currentEvent->getAuthorName()),
                        'authorId' => $currentEvent->getAuthorId(),
                        'level' => $currentEvent->getDerivedEventLevel(),
                        'completed' => $currentEvent->getCompleted(),
                    ),
                    'links' => $bookingButtons,
                    'styles' => array(
                        'visible' => $cssClass,
                    ),
                    'attendance' => array(
                        'icon' => false,
                        'status' => false,
                        'requestButton' => false,
                        'listButton' => false,
                    ),
                    'booking' => array(
                        'status' => $currentEvent->getBooking()->getStatus()->jsonSerialize(),
                    ),
                )
            );

            echo json_encode($event);
            echo ",";
        }
    }

    /**
     * If the Publish Date hasn't been reached yet, only show the events to Superusers
     * in a different way so they can still edit/click them. Otherwise, they don't appear
     * at all for everyone else
     *
     * @param $currentEvent
     *
     * @return bool
     */
    private function isVisible($currentEvent) {
        $isFutureEvent = ($currentEvent->getPublishDate()->getUnixTimestamp() >= time());
        $isVisibleToUser = ($this->getGroupHandler()->isSuperuser() || $currentEvent->isOwner() || (is_numeric($currentEvent->getCategoryId()) && ($this->getGroupHandler()->getPermission($currentEvent->getCategoryId())) > READ_ACCESS));

        if ($isFutureEvent && !($isVisibleToUser)) {
            return false;
        }

        return true;
    }
}
