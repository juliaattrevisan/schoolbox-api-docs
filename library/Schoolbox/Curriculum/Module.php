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
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Permission\Access\UserAccess;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{
    protected $basename = 'curriculum';

    /**
     * {@inheritdoc}
     */
    public function initSubmodules(ContainerInterface $injector, $basePath)
    {
        $importModule = new Import\Module($injector);
        $importModule->init($basePath.'/import');
        $areaModule = new LearningArea\Module($injector);
        $areaModule->init($basePath.'/area');
        $levelModule = new LearningLevel\Module($injector);
        $levelModule->init($basePath.'/level');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->attachResourceRoutes($router, $basePath);
        $this->addGetRoute($router, 'search', $basePath, '/search');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Add the item to params, if ID is in the route.
        // TODO DI this
        $nodeMapper = new Node\Mapper();
        $matcher->add(
            '/^curriculum\\./',
            function ($params) use ($nodeMapper) {
                if (array_key_exists('id', $params)) {
                    return array(
                        'item' => $nodeMapper->findById($params['id']),
                    );
                }
                return array();
            }
        );
    }

    /**
     * Everyone has read permission to a curriculum, but only users with write
     * permission to the curriculum service may create or modify them.
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $authorizer->addRule('/^curriculum\\..*/', new Rule\IsLoggedIn());
        $authorizer->addRule(
            '/^curriculum\\.(?:edit|add|delete|create|update|replace)/',
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(
                    UserAccess::SERVICE_CURRICULUM,
                    UserAccess::ACCESS_WRITE
                )
            )
        );
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
        $injector->setters['Schoolbox\\Curriculum\\Controller']['setAreaMapper']
            = $injector->lazyNew('Schoolbox\\Curriculum\\LearningArea\\Mapper');
        $injector->setters['Schoolbox\\Curriculum\\Controller']['setLevelMapper']
            = $injector->lazyNew('Schoolbox\\Curriculum\\LearningLevel\\Mapper');
    }
}
