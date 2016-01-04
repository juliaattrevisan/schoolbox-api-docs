<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Role;
use Twig_Error_Loader;
use Twig_Loader_Chain;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'rubric';


    /**
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addPostRoute($router, 'create', $basePath, "");

        $this->addGetRoute($router, 'testGeneric', $basePath, "/test");
        $this->addGetRoute($router, 'testEmpty',   $basePath, "/test/empty");
        $this->addGetRoute($router, 'testBox',     $basePath, "/test/box/{boxId}");
        $this->addGetRoute($router, 'testFolder',  $basePath, "/test/folder/{folderId}");
    }


    /**
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                '^create$',
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            );
    }


    /**
     * @param Twig_Loader_Chain $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
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

        // dependencies
        $injector->setters[Controller::class]['setFactory'] = $injector->lazyNew(Factory::class);
        $injector->setters[Controller::class]['setMapper']  = $injector->lazyNew(Mapper::class);

    }


}
