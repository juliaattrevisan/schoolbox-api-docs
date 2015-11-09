<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Twig_Error_Loader;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{
    protected $basename = 'messages';

    /**
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'configure', $basePath, '');
        $this->addPostRoute($router, 'saveConfig', $basePath, '');
    }


    /**
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermissionForBasename($authorizer, '.*', new Rule\IsLoggedIn());
    }


    /**
     * @param Twig_Loader_Chain $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        // TODO inherit from an Admin baseclass to setup subnav
        $this->addTemplatePathForBasename($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:messages',
            $injector->lazyNew(Controller::class)
        );
    }

}