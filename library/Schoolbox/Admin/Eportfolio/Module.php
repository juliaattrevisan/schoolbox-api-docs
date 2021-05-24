<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Eportfolio;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Eportfolio\PageMapper as EportfolioPageMapper;
use Alaress\Twig\Loader\IChainLoader;

/**
 * The module for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Menu\Side
 */
class Module extends BaseModule
{
    protected $basename = 'adminEportfolio';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/deploy' => 'getDeploy',
                '/deploy/progress/{id}/{skip}' => 'getImportProgress',
                '/user/{userId}/pages' => 'getUserPages',
            ],
            'POST' => [
                '/deploy' => 'postDeploy'
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * @inheritdoc
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser())
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'eportfolioImportMapper' => $this->getMapperFactory()->getLazy(Import\Mapper::class),
                'eportfolioImportLogMapper' => $this->getMapperFactory()->getLazy(Import\Log\Mapper::class),
                'eportfolioPageMapper' => $this->getMapperFactory()->getLazy(EportfolioPageMapper::class),
                'roleMapper' => $this->getMapperFactory()->getLazy(\Schoolbox\RoleMapper::class),
                'campusMapper' => $this->getMapperFactory()->getLazy(\Schoolbox\CampusMapper::class),
                'userMapper' => $this->getMapperFactory()->getLazy(\Schoolbox\UserMapper::class)
            ])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
