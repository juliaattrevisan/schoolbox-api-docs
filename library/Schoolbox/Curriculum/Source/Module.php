<?php
/**
 * Definition for the curriculum import submodule.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\Source;

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
    public function initRoutes(Router $router, $basePath)
    {
        $router->attachResource('curriculumSource', $basePath);
        $router->attachResourcePostAlias('curriculumSource', $basePath);
    }

    /**
     * For now, curricula are only editable by superusers.
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $authorizer->addRule('/^curriculumSource\\..*/', new Rule\IsLoggedIn());
        $authorizer->addRule('/^curriculumSource\\..*/', new Rule\IsSuperuser());
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__ . '/templates', 'curriculumSource');
        $templateLoader->addLoader($loader);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:curriculumSource',
            $injector->lazyNew('Schoolbox\\Curriculum\\Source\\Controller')
        );
        $injector->setters['Schoolbox\\Curriculum\\Import\\Controller']['setMapper']
            = $injector->lazyNew('Schoolbox\\Curriculum\\Source\\Mapper');
    }
}
