<?php
/**
 * Resource management component
 *
 * Uses Twig for output instead of the PHPLib suckfest.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking\Component;

use Component_Dashboard_ControllerAbstract;
use Schoolbox\Collection;
use Schoolbox\ResourceBooking\Booking;
use Aura\Di;
use Twig_Environment;

class ResourceManagement extends Component_Dashboard_ControllerAbstract {

    /**
     * @var Booking\Mapper
     */
    protected $bookingMapper;

    /**
     * @var Twig_Environment
     */
    protected $templateEngine;

    /**
     * @var Collection|Booking\Model[]
     */
    protected $bookings;

    public function execute() {
        /** @var Di\ContainerInterface $di */
        global $di;
        $this->templateEngine = $di->get('templateEngine');
        $this->bookingMapper  = Booking\Mapper::getInstance();

        // Get next few days of bookings
        // Get only bookings for the current user
        $days      = max(1, $this->getConfig('days'));
        $startTime = strtotime("00:00",            $_SERVER['REQUEST_TIME']);
        $endTime   = strtotime("00:00 +$days day", $_SERVER['REQUEST_TIME']);

        // Default to showing only items I have moderator permission for.
        // We know the rules:
        // use AuthChecker->check('resourceBooking.approve', {id: booking.id})
        // Cache the booking, too, so I don't fetch it every single check.
        $this->bookings = $this->bookingMapper->findForTimespan(
            $startTime,
            $endTime,
            array(Booking\Status\Model::STATUS_PENDING)
        );
        return true;
    }

    public function renderTemplate($templateHandle) {

        // Don't mess about with blocks, shove this into Twig
        if (!$this->loadTemplate($templateHandle, 'resourceManagement')) {
            return false;
        }

        $util     = new Booking\Util\EventConverter();
        $bookings = $this->bookings->map(function($item) use($util) {
            return array(
                'booking' => $item,
                'event'   => $util->convertEventToCalendarEvent($item->getEvent()),
            );
        });

        // Where do I get a Twig loader from?
        // I should get the dependency injected one, but that means getting the
        // global DI container.
        // I could always get the controller out of DI.
        $this->setVar(
            'COMPONENT_CONTENT',
            $this->templateEngine->render(
                '@resourceBooking/component/resourceManagement.twig',
                array(
                    'bookings'  => $bookings,
                    'component' => $this,
                )
            )
        );
        return true;

    }

}
