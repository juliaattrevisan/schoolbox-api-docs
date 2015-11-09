<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\API;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Alaress\Web\Router\RouteCollection;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;

class Module extends BaseModule {

    protected $basename = 'api';

    /**
     * @param Router|RouteCollection $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addPostRoute($router, 'register', $basePath, '/register/{provider}');
    }


    /**
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreloadForBasename(
            $matcher,
            'register',
            function ($params) {
                return array(
                    'provider' => $params['provider'],
                );
            }
        );
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:api',
            $injector->lazyNew('Schoolbox\\API\\Controller')
        );

        $injector->setters['Schoolbox\\API\\Controller']['setAuth']
            = $injector->lazyNew('Authenticator');

        $injector->setters['Schoolbox\\API\\Controller']['setRegistryMapper']
            = $injector->lazyNew('Schoolbox\\MobileApp\\RegistryMapper');

    }

}