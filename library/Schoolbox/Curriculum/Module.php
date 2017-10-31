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
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\Curriculum\LearningArea\Mapper as LearningAreaMapper;

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

        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) {
                if (array_key_exists('id', $params)) {
                    return array(
                        'item' => $this->getNodeMapper()->findById($params['id']),
                    );
                }
                return array();
            }
        );
    }

    /**
     * Everyone has read permission to a curriculum, but only users with write
     * permission to the curriculum service may create or modify them.
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                '(?:edit|add|delete|create|update|replace)',
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
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class)
        );
        $injector->setters[Controller::class]['setNodeMapper']
            = $this->getMapperFactory()->getLazy(NodeMapper::class);
    }

    /* Getters / Setters
     */

    /**
     * @return NodeMapper
     */
    protected function getNodeMapper()
    {
        return $this->getMapperFactory()->get(NodeMapper::class);
    }
}
