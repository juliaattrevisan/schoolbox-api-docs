<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\User;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
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
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Resource\Folder\Mapper::class),
            $this->getMapperFactory()->get(Access::class),
            $this->injector->get('externalUser'),
            $this->injector->get('externalClass'),
            $this->getMapperFactory()->get(ProfileButtonMapper::class),
            $this->getMapperFactory()->get(\Timetable_ColourMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\ProfessionalDevelopment\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Pastoral\Type\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Pastoral\SubType\Mapper::class),
            $this->injector->get('pastoralDataSource'),
            $this->getMapperFactory()->get(\Schoolbox\Assessment\Term\Mapper::class),
            new \Schoolbox\External\User(
                $this->injector->get('config')->get('external_type'),
                new \External_Class(),
                new \External_User(),
                $this->getMapperFactory()->get(UserMapper::class)
            ),
            $this->getMapperFactory()->get(\Schoolbox\Admin\Profile\Editor\Field\Mapper::class),
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
