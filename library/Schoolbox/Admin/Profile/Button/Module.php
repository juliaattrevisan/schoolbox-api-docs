<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Button;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Menu\Icon\Mapper as IconMapper;
use Schoolbox\Profile\Button\Mapper as ProfileButtonMapper;
use Schoolbox\Profile\Button\Campus;
use Schoolbox\Profile\Button\Role;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;

/**
 * The module for managing custom profile buttons through the admin section.
 *
 * @package Schoolbox\Admin\Profile\Button
 */
class Module extends BaseModule
{
    protected $basename = 'adminProfileButton';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath): void
    {
        $routes = [
            'GET' => [
                '' => 'getIndex',
                '/create' => 'getCreate',
                '/modify/{id}' => 'getModify',
            ],
            'POST' => [
                '/delete' => 'postDelete',
                '/order' => 'postOrder',
                '/modify' => 'postModify',
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
    public function initRoutePermissions(Authorizer $authorizer): void
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(IconMapper::class),
            $this->getMapperFactory()->get(ProfileButtonMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(Campus\Availability\Mapper::class),
            $this->getMapperFactory()->get(Role\Availability\Mapper::class),
            $this->getMapperFactory()->get(Campus\Visibility\Mapper::class),
            $this->getMapperFactory()->get(Role\Visibility\Mapper::class),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
