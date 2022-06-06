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
        $bookingApi->setStatus(
            [
                Booking\Status\Model::STATUS_PENDING,
            ]
        );

        // Still need to check if user actually has any bookable items.
        // If they don't, then don't display this component.
        if (!$bookingApi->getUserCanModerate()) {
            return false;
        }
        $bookingEvents = $bookingApi->getEvents($startTime, $endTime);

        // Group bookings by their item, and order within that list by date
        // (They should be date-ordered already)
        $groupedBookings = [];
        foreach ($bookingEvents as $event) {
            if ($event instanceof Event) {
                $itemId = $event->getBooking()->getResource()->getId();
                if (empty($groupedBookings[$itemId])) {
                    $groupedBookings[$itemId] = [
                        'resource'      => $event->getBooking()->getResource(),
                        'bookingEvents' => [],
                    ];
                }
                $groupedBookings[$itemId]['bookingEvents'][] = $event;
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
