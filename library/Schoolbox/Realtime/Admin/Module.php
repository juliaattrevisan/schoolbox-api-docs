<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Admin;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Twig_Error_Loader;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{
    protected $basename = 'adminMessages';

    /**
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'customConfig', $basePath, "/configure/custom");
        $this->addGetRoute($router, 'configure', $basePath, "/configure{/roletype}");
        $this->addPostRoute($router, 'saveConfig', $basePath, "/configure{/roletype}");
    }


    /**
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        // TODO import this into an Admin baseclass so you don't have to do it for all admin pages
        $this->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }


    public function initRoutePreloads(PathMatcher $matcher)
    {
//        $matcher->add(
//            '/^adminMessages\\.configure$/',
//            function ($params) {
//                return [ '' ];
//            }
//        );
    }


    /**
     * @param Twig_Loader_Chain $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        // TODO inherit from an Admin baseclass to setup subnav
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class)
        );
    }

}