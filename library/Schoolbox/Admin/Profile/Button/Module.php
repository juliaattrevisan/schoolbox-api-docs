<?php
declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Button;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;

/**
 * The module for managing school terms through the admin section.
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
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
