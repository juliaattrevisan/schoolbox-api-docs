<?php
/**
 * Routes for the resource browser
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Aura\Di;
use Aura\Router\Router;
use Alaress\Authorizer;

/** @var Di\Container $di */
/** @var Router $router */
/** @var Authorizer\Authorizer $authorizer */
global $di;

$categoryModule = new Schoolbox\ResourceBooking\Category\Module($di);
$categoryModule->init('/resource-booking/category');

$itemModule = new Schoolbox\ResourceBooking\Item\Module($di);
$itemModule->init('/resource-booking/item');
