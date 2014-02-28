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
$router = $di->get('router');
// attachRedirect('/resource-booking', 'resourceItem.browse')
$router->attachResource('resourceItem', '/resource-booking/item');
$router->attachResourcePostAlias('resourceItem', '/resource-booking/item');
$router->attachResource('resourceCategory', '/resource-booking/category');
$router->attachResourcePostAlias('resourceCategory', '/resource-booking/category');

// Set up authorization
$authorizer = $di->get('authorizer');
$authorizer
    ->addRule('/^resourceItem(?:$|\.)/', new Authorizer\Rule\IsLoggedIn())
    ->addRule('/^resourceCategory(?:$|\.)/', new Authorizer\Rule\IsLoggedIn())
    ->addRule('/^resourceItem\.(?:create|edit|delete)/', new Authorizer\Rule\IsSuperuser())
    ->addRule('/^resourceCategory\.(?:create|edit|delete)/', new Authorizer\Rule\IsSuperuser())
;

// Set up the controller
// The injector is going to be generic to all controllers, so have some better
// way of setting this up.
// Twig needs to be injected into all controllers too.
// Shouldn't there be a controller factory?
// I'm going to need some method of adding pre and post processors to the controller renderer
// They may need to muddle the request and response object (example - redirect to a login or error page)
$di->setters['Schoolbox\\ResourceBooking\\Item\\Controller']['setMapper']
    = $di->lazyNew('Schoolbox\\ResourceBooking\\Item\\Mapper');
$di->set('controller:resourceItem', $di->lazyNew('Schoolbox\\ResourceBooking\\Item\\Controller'));
$di->setters['Schoolbox\\ResourceBooking\\Category\\Controller']['setMapper']
    = $di->lazyNew('Schoolbox\\ResourceBooking\\Category\\Mapper');
$di->set('controller:resourceCategory', $di->lazyNew('Schoolbox\\ResourceBooking\\Category\\Controller'));
