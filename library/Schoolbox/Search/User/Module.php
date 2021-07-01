<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\User;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Profile\Button\Mapper as ProfileButtonMapper;

/**
 * The module for user profile page.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'searchUser';


    /**
     * @inheritDoc
     */
    protected function registerRoutes(): void
    {
        $preloadUser = function ($params) {
            return $this->preloadUser($params);
        };

        $this->register(self::METHOD_GET, '/{userId:\d+}', 'getProfile', null, $preloadUser);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'userMapper'                    => $this->getMapperFactory()->getLazy(UserMapper::class),
                    'roleMapper'                    => $this->getMapperFactory()->getLazy(RoleMapper::class),
                    'folderMapper'                  => $this->getMapperFactory()->getLazy(\Schoolbox\Resource\Folder\Mapper::class),
                    'access'                        => $this->getMapperFactory()->getLazy(Access::class),
                    'externalUser'                  => $injector->lazyGet('externalUser'),
                    'externalClass'                 => $injector->lazyGet('externalClass'),
                    'profileButtonMapper'           => $this->getMapperFactory()->getLazy(ProfileButtonMapper::class),
                    'timetableColourMapper'         => $this->getMapperFactory()->getLazy(\Timetable_ColourMapper::class),
                    'professionalDevelopmentMapper' => $this->getMapperFactory()->getLazy(\Schoolbox\ProfessionalDevelopment\Mapper::class),
                ]
            )
        );
    }


    /**
     * @inheritDoc
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        // Build a Faker instance and shove it in
        $injector->params[Controller::class] = [
        ];
    }

    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidPath
     */
    private function preloadUser(array $params): array
    {
        if (($params['user'] ?? null) instanceof User) {
            return $params;
        }

        if (!\array_key_exists('userId', $params)) {
            throw new InvalidPath("No 'userId' found in route params");
        }

        try {
            return ['user' => $this->getUserMapper()->findById($params['userId'])];
        } catch (Mapper\Exception $e) {
            throw new InvalidPath('User Not Found', 0, $e);
        }
    }

    /**
     * @return UserMapper|Mapper
     */
    private function getUserMapper(): UserMapper
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

}
