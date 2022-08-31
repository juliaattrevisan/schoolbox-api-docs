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
use Alaress\Web\Controller\Base;
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
                '/deploy/progress/{id}{/skip}' => 'getImportProgress',
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(EportfolioPageMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\RoleMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\CampusMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\UserMapper::class),
            $this->getMapperFactory()->get(Import\Mapper::class),
            $this->getMapperFactory()->get(Import\Log\Mapper::class)
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
