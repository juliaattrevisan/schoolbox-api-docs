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

/** @var Di\Container $di */
/** @var Router $router */
global $di;
$router = $di->get('router');
$router->attachResource('resourceCategory', '/resource/category');

// POST aliases for update (PATCH), replace (PUT) and delete (DELETE) routes.
// Necessary because HTML forms support only POST and GET HTTP methods.
// I'm not keen on the RoR method of supporting extra methods (passing method
// as a POST parameter), as this means that extra form inputs need to be added.
$router->attach('resourceCategory', '/resource/category', function($subroute) {
    /** @var Router $subroute */
    $subroute->setTokens(array(
        'id'     => '\d+',
        'format' => '(\.[^/]+)?'
    ));
    $subroute
        ->addPost('update:post', '/{id}/update')
        ->addValues(array('action' => 'update'));
    $subroute
        ->addPost('replace:post', '/{id}/replace')
        ->addValues(array('action' => 'replace'));
    $subroute
        ->addPost('delete:post', '/{id}/delete')
        ->addValues(array('action' => 'delete'));
});

// Set up the controller
// The injector is going to be generic to all controllers, so have some better
// way of setting this up.
// Twig needs to be injected into all controllers too.
// Shouldn't there be a controller factory?
// I'm going to need some method of adding pre and post processors to the controller renderer
// They may need to muddle the request and response object (example - redirect to a login or error page)
$di->setters['Schoolbox\\ResourceBooking\\Category\\Controller']['setMapper']
    = $di->lazyNew('Schoolbox\\ResourceBooking\\Category\\Mapper');
$di->set('controller:resourceCategory', $di->lazyNew('Schoolbox\\ResourceBooking\\Category\\Controller'));
