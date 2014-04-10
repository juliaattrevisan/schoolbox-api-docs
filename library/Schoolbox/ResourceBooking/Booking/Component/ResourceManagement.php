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
use Schoolbox\Column\Collection;
use Schoolbox\ResourceBooking\Booking\Mapper;
use Aura\Di;
use Schoolbox\ResourceBooking\Booking\Model;
use Twig_Environment;

class ResourceManagement extends Component_Dashboard_ControllerAbstract {

    /**
     * @var Mapper
     */
    protected $bookingMapper;

    /**
     * @var Twig_Environment
     */
    protected $templateEngine;

    /**
     * @var Collection|Model[]
     */
    protected $bookings;

    public function execute() {
        /** @var Di\ContainerInterface $di */
        global $di;
        $this->templateEngine = $di->get('templateEngine');
        $this->bookingMapper  = Mapper::getInstance();

        // Get next few days of bookings
        // Get only bookings for the current user
        $days      = max(1, $this->getConfig('days'));
        $startTime = strtotime("00:00",            $_SERVER['REQUEST_TIME']);
        $endTime   = strtotime("00:00 +$days day", $_SERVER['REQUEST_TIME']);;
        $this->bookings = $this->bookingMapper->findForTimespan($startTime, $endTime);
        return true;
    }

    public function renderTemplate ($templateHandle) {

        // Don't mess about with blocks, shove this into Twig
        if (!$this->loadTemplate($templateHandle, 'resourceManagement')) {
            return false;
        }

        // Where do I get a Twig loader from?
        // I should get the dependency injected one, but that means getting the
        // global DI container.
        // I could always get the controller out of DI.
        $this->setVar(
            'COMPONENT_CONTENT',
            $this->templateEngine->render(
                '@resourceBooking/component/resourceManagement.twig',
                array('bookings' => $this->bookings)
            )
        );
        return true;

    }

}
