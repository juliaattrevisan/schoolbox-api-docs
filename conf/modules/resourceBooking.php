<?php
/**
 * Configuration for the resource booking system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/** @var Aura\Di\Container $di */
global $di;
if ($di->get('config')->get('resource_booking_enabled')) {
    $categoryModule = new Schoolbox\ResourceBooking\Category\Module($di);
    $categoryModule->init('/resource-booking/category');

    $itemModule = new Schoolbox\ResourceBooking\Item\Module($di);
    $itemModule->init('/resource-booking/item');

    $bookingModule = new Schoolbox\ResourceBooking\Booking\Module($di);
    $bookingModule->init('/resource-booking/booking');
}
