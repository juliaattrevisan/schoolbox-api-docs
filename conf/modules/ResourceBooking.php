<?php

/**
 * Configuration for the resource booking system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\ResourceBooking\Category;
use Schoolbox\ResourceBooking\Booking;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Report;

class ResourceBooking implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        if ($di->get('config')->get('resource_booking_enabled')) {
            $categoryModule = new Category\Module($di);
            $categoryModule->registerModule('/resource-booking/category');
//            $categoryModule->init('/resource-booking/category');

            $itemModule = new Item\Module($di);
            $itemModule->registerModule('/resource-booking/item');
//            $itemModule->init('/resource-booking/item');

            $bookingModule = new Booking\Module($di);
            $bookingModule->registerModule('/resource-booking/booking');
//            $bookingModule->init('/resource-booking/booking');

            $reportModule = new Report\Module($di);
            $reportModule->registerModule('/resource-booking/report');
//            $reportModule->init('/resource-booking/report');
        }
    }
}
