<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Admin;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Map;
use Twig\Error\LoaderError;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    protected $basename = 'adminNotifications';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $this->register('GET', '/apps', 'getApps');
        $this->register('GET', '/apps/new', 'getCreateAppForm');
        $this->register('POST', '/apps', 'postModifyApp');
        $this->register('POST', '/apps/delete', 'postDeleteApp');
        $this->register('POST', '/settings/apply-default', 'postApplyDefaultSettings');
    }


    /**
     * @param string $basePath
     */
    public function initRoutes(string $basePath)
    {
        $this->addGetRoute('customConfig', $basePath, "/configure/custom");
        $this->addGetRoute('configure', $basePath, "/configure{/roletype}");
        $this->addGetRoute('testPush', $basePath, '/puush{/userId}');
        $this->addPostRoute('saveConfig', $basePath, "/configure{/roletype}");
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
//            '/^adminNotifications\\.configure$/',
//            function ($params) {
//                return [ '' ];
//            }
//        );
    }


    /**
     * @param IChainLoader $templateLoader
     * @throws LoaderError
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        // TODO inherit from an Admin baseclass to setup subnav
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller($this->injector->get('filesystem.storageProxy'));
    }
}
