<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\User;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
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

        $this->register(self::METHOD_GET, '/{userId:\d+}', 'getProfile', new IsLoggedIn(), $preloadUser);
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
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws NotFound
     */
    private function preloadUser(array $params): array
    {
        if (($params['user'] ?? null) instanceof User) {
            return $params;
        }

        if (!\array_key_exists('userId', $params)) {
            throw new NotFound("No 'userId' found in route params");
        }

        try {
            $user = $this->getUserMapper()->findById($params['userId']);
            if ($user->getRole()->isGuest()) {
                throw new NotFound('User Not Found', 0);
            }
            return ['user' => $user];
        } catch (Mapper\Exception $e) {
            throw new NotFound('User Not Found', 0, $e);
        }
    }

    /**
     * @return UserMapper
     */
    private function getUserMapper(): UserMapper
    {
        /** @var UserMapper $userMapper */
        $userMapper = $this->getMapperFactory()->get(UserMapper::class);
        return $userMapper;
    }
}
