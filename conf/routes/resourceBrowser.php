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
//    ->add('resourceCategory', '/resourceCategory/?')
//    ->addValues(array('controller' => 'resourceCategory', 'action' => 'list',))
//;
