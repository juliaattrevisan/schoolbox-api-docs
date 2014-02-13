<?php
/**
 * Routes for the resource browser
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Aura\Di;

/** @var Di\Container $di */
$router = $di->get('router');
$router->attachResource('resourceCategory', '/resourceCategory');

// Set up the controller
// The injector is going to be generic to all controllers, so have some better
// way of setting this up.
// Twig needs to be injected into all controllers too.
// Shouldn't there be a controller factory?
// I'm going to need some method of adding pre and post processors to the controller renderer
// They may need to muddle the request and response object (example - redirect to a login or error page)
$di->set('controller:resourceCategory', $di->lazyNew('Schoolbox\\ResourceBooking\\Category\\Controller'));
