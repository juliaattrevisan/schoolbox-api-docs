<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Usage;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

/**
 * Class Module
 *
 * @package Schoolbox\Example
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminUsage';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Anyone can see the form
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $preloadUser = function (array $params) {
            return $this->preloadUser($params);
        };

        $this->register('GET', '', 'getIndex', $getPerms);

        /* Global Usage payload endpoints
         */
        $this->register('GET', '/system', 'getSystemPayload', $getPerms);
        $this->register('GET', '/role', 'getRolePayload', $getPerms);
        $this->register('GET', '/total', 'getTotalPayload', $getPerms);
        $this->register('GET', '/user', 'getUserPayload', $getPerms);
        $this->register('GET', '/type', 'getFileTypePayload', $getPerms);

        /* Specific Usage payload endpoints
         */
        $this->register('GET', '/specific/{userId}', 'getSpecificUser', $getPerms, $preloadUser);
        $this->register('GET', '/specific/{userId}/system', 'getSpecificUserSystemPayload', $getPerms, $preloadUser);
        $this->register('GET', '/specific/{userId}/total', 'getSpecificUserTotalPayload', $getPerms, $preloadUser);
        $this->register('GET', '/specific/{userId}/file', 'getSpecificUserFilePayload', $getPerms, $preloadUser);
        $this->register('GET', '/specific/{userId}/type', 'getSpecificUserFileTypePayload', $getPerms, $preloadUser);
    }


    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadUser(array $params): array
    {
        try {
            $user = $this->getUserMapper()->findById($params['userId']);
        } catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'user' => $user,
        ];
    }


    /**
     * @inheritdoc
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
