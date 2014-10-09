<?php
/**
 * Definition for the curriculum mapping module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{
    /**
     * {@inheritdoc}
     */
    public function initSubmodules(ContainerInterface $injector, $basePath)
    {
        $importModule = new Import\Module($injector);
        $importModule->init($basePath.'/import');
        $sourceModule = new Source\Module($injector);
        $sourceModule->init($basePath.'/source');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $router->attachResource('curriculum', $basePath);
        $router->attachResourcePostAlias('curriculum', $basePath);
//        $router->addGet('curriculum.browse', $basePath . '/');
//        // Attach standard REST routes
//        $router->attachResource('tile', $basePath);
//        $router->attachResourcePostAlias('tile', $basePath);
//        // but have separate add tile paths
//        $router->addGet('tile.add', $basePath.'/add/{componentId}');
//        $router->addPost('tile.create', $basePath.'/add/{componentId}');
    }

    /**
     * For now, curricula are only editable by superusers.
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $authorizer->addRule('/^curriculum\\..*/', new Rule\IsLoggedIn());
        $authorizer->addRule('/^curriculum\\..*/', new Rule\IsSuperuser());
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__ . '/templates', 'curriculum');
        $templateLoader->addLoader($loader);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:curriculum',
            $injector->lazyNew('Schoolbox\\Curriculum\\Controller')
        );
        $injector->setters['Schoolbox\\Curriculum\\Controller']['setNodeMapper']
            = $injector->lazyNew('Schoolbox\\Curriculum\\Node\\Mapper');
    }
}
