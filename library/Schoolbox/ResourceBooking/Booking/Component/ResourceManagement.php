<?php

/**
 * Resource management component
 *
 * Uses Twig for output instead of the PHPLib suckfest.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking\Component;

use Calendar\Event\Type\ResourceBooking\Event;
use Component_Dashboard_ControllerAbstract;
use Calendar_Api_EventType_ResourceBooking_Moderation_Default;
use Schoolbox\ResourceBooking\Booking;
use Twig\Environment;
use Calendar_Api_Config_Mapper;

class ResourceManagement extends Component_Dashboard_ControllerAbstract
{
    /**
     * @var Booking\Mapper
     */
    protected $bookingMapper;

    /**
     * @var Environment
     */
    protected $templateEngine;

    /**
     * @var array
     */
    protected $bookings;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        //Ensure ResourceBooking module is enabled
        if (0 == $this->getConfigLoader()->get('resource_booking_enabled')) {
            return false;
        }

        $this->templateEngine = $this->getSitePage()->getDi()->get('templateEngine');
        $this->bookingMapper  = Booking\Mapper::getInstance();

        // Get next few days of bookings
        // Get only bookings for the current user
        $days = $this->getConfig('days');
        if ($days <= 0) {
            $days = 30;
        }
        $startTime = strtotime("00:00", $_SERVER['REQUEST_TIME']);
        $endTime   = strtotime("00:00 +$days day", $_SERVER['REQUEST_TIME']);

        $config     = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_ResourceBooking_Moderation_Default::class);
        $bookingApi = new Calendar_Api_EventType_ResourceBooking_Moderation_Default(
            $config->getId(),
            $this->getUser(),
            $this->getUser()
        );

        // Still need to check if user actually has any bookable items.
        // If they don't, then don't display this component.
        if (!$bookingApi->getUserCanModerate()) {
            return false;
        }
        $bookingEvents = $bookingApi->getEvents($startTime, $endTime);
        $capacityAllocation = new Booking\CapacityAllocation();

        // Group bookings by their item, and order within that list by date
        // (They should be date-ordered already)
        $eventsByResource = []; // init grouping array sorted
        $groupedBookings = []; // final array grouped by day with only the events we care about
        foreach ($bookingEvents as $event) {
            if ($event instanceof Event) {
                // Due to hydration differences between resource bookings and timetable events, we need to extract the resource ID
                $itemId = $event->getBooking()->getResource()->getId();
                if (empty($eventsByResource[$itemId])) {
                    $eventsByResource[$itemId] = [];
                    $groupedBookings[$itemId] = [
                        'resource'      => $event->getBooking()->getResource(),
                        'bookingEvents' => [],
                    ];
                }
                $eventsByResource[$itemId][] = $event;
            }
        }
        // Step 2: For each resource, sort events by start time and check for overlaps
        foreach ($eventsByResource as $resourceID => $resourceEvents) {
            // Sort by start time
            usort($resourceEvents, function ($a, $b) {
                return $a->getStartTime()->compare($b->getStartTime());
            });
            foreach ($resourceEvents as $eventA) {
                /** @var Event $eventA */
                if (empty($groupedBookings[$resourceID]['bookingEvents'][$eventA->getStartTime()->getDateOnly()])) {
                    $groupedBookings[$resourceID]['bookingEvents'][$eventA->getStartTime()->getDateOnly()] = [
                        'dateFormat' => [
                            'day' => $eventA->getStartTime()->getDay(),
                            'monthName' => $eventA->getStartTime()->getUsingFormat('M'),
                            'dayName' => $eventA->getStartTime()->getUsingFormat('D'),
                        ],
                        'events' => [],
                    ];
                }

                // we default to showing all pending bookings, so we can see what we need to approve
                if ($eventA->getBooking()->getStatus()->isPending()) {
                    $eventAHash = spl_object_hash($eventA);
                    $groupedBookings[$resourceID]['bookingEvents'][$eventA->getStartTime()->getDateOnly()]['events'][$eventAHash] = [
                        'event' => $eventA,
                        'clash' => []
                    ];

                    $blockingEvents = $capacityAllocation->getBlockingApprovedBookingEvents($eventA, $resourceEvents);
                    foreach ($blockingEvents as $blockingEvent) {
                        $groupedBookings[$resourceID]['bookingEvents'][$eventA->getStartTime()->getDateOnly()]['events'][$eventAHash]['clash'][] = $blockingEvent;
                    }
                }

                // if no events, ignore this
                if (empty($groupedBookings[$resourceID]['bookingEvents'][$eventA->getStartTime()->getDateOnly()]['events'])) {
                    unset($groupedBookings[$resourceID]['bookingEvents'][$eventA->getStartTime()->getDateOnly()]);
                }
            }
        }

        usort($groupedBookings, function ($a, $b) {
            return strcmp($a['resource']->getName(), $b['resource']->getName());
        });
        $this->bookings = $groupedBookings;
        return true;
    }

    public function renderTemplate($templateHandle)
    {

        /* This is a Dashboard component
         * redirect back here after potentially modifying a resource booking
         */
        $returnUrl = \Schoolbox\Learning\Assessment\Redirect::encode('/');

        // Don't mess about with blocks, shove this into Twig
        if (!$this->loadTemplate($templateHandle, 'resourceManagement')) {
            return false;
        }

        $this->renderTwigTemplate(
            $templateHandle,
            '@resourceBooking/component/resourceManagement.twig',
            [
                'bookingsByResource' => $this->bookings,
                'component'          => $this,
                'returnUrl'          => $returnUrl
            ],
            true,
            false
        );
        return true;
    }
}
