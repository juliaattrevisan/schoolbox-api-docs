<?php

/**
 * Configuration for the resource booking system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\ResourceBooking\Category;
use Schoolbox\ResourceBooking\Booking;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Report;

class ResourceBooking implements ModuleConfigInterface
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();

        if ($di->get('config')->get('resource_booking_enabled')) {
            $categoryModule = new Category\Module($di);
            $categoryModule->init('/resource-booking/category');

            $itemModule = new Item\Module($di);
            $itemModule->init('/resource-booking/item');

            $bookingModule = new Booking\Module($di);
            $bookingModule->init('/resource-booking/booking');

            $reportModule = new Report\Module($di);
            $reportModule->init('/resource-booking/report');
        }
    }
}
