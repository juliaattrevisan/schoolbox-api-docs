<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Intellischool;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Admin\Intellischool\Controller;
use Schoolbox\Intellischool\JwtFactory;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminIntellischool';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        // Various permissions
        $basePermission = new Rule\All(
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL, $this->getConfig()),
        );

        /* Anyone can see the form
         */
        $getPerms = new Rule\All(
            $basePermission,
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '/console', 'getConsole', $getPerms, null);
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        /** @var Container $injector */
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'jwtFactory' => $injector->lazy(function () {
                        return new JwtFactory(
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL),
                        );
                    }),
                ]
            )
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class),
            'userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class)
        ];
    }


    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}
