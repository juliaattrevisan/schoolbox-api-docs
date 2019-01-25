<?php
declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Button;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
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

        foreach ($routes['GET'] as $path => $alias)
            $this->addGetRoute($router, $alias, $basePath, $path);

        foreach ($routes['POST'] as $path => $alias)
            $this->addPostRoute($router, $alias, $basePath, $path);
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
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class), [
            'errorMapper' => $this->getMapperFactory()->getLazy(\Schoolbox\Error\Mapper::class),
            'iconMapper'   => $this->getMapperFactory()->getLazy(IconMapper::class),
            'profileButtonMapper' => $this->getMapperFactory()->getLazy(ProfileButtonMapper::class),
            'roleAvailabilityMapper' => $this->getMapperFactory()->getLazy(Role\Availability\Mapper::class),
            'campusAvailabilityMapper' => $this->getMapperFactory()->getLazy(Campus\Availability\Mapper::class),
            'roleVisibilityMapper' => $this->getMapperFactory()->getLazy(Role\Visibility\Mapper::class),
            'campusVisibilityMapper' => $this->getMapperFactory()->getLazy(Campus\Visibility\Mapper::class),
            'roleMapper' => $this->getMapperFactory()->getLazy(RoleMapper::class),
            'campusMapper' => $this->getMapperFactory()->getLazy(CampusMapper::class),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
