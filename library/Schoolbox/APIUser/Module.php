<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIUser;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use HouseMapper;
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\CampusMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'apiuser';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /* Only superusers can access the Groups API
         */
        $hasApiAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );
        //allow the user to see themselves, and allow superuser to also see users
        $allowSelfAdminAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\IsUser('userId')
            )
        );

        $preloadUser = function ($params) {
            return $this->preloadUser($params);
        };

        $this->register('GET', '/{userId}', 'apiUserGet', $allowSelfAdminAccess, $preloadUser);
        $this->register('DELETE', '/{userId}', 'apiUserDelete', $hasApiAccess, $preloadUser);
        $this->register('POST', '', 'apiUserPost', $hasApiAccess);
        $this->register('PUT', '/{userId}', 'apiUserPut', $hasApiAccess, $preloadUser);
        $this->register('PATCH', '/{userId}', 'apiUserPatch', $hasApiAccess, $preloadUser);
        $this->register('GET', '', 'apiUserSearch', $hasApiAccess);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getAuthorizer(),
            $this->getRouter()->getGenerator(),
            $this->getMapperFactory()->get(UserAccessMapper::class),
            $this->getMapperFactory()->get(GroupMapper::class),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(\Timetable_ColourMapper::class),
            $this->getMapperFactory()->get(RerouteOverrideMapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(HouseMapper::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(YearLevelMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            new Validator(),
        );
    }

    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws SchoolboxException
     */
    private function preloadUser(array $params): array
    {
        try {
            $user = $this->getUserMapper()->findById($params['userId']);
        } catch (MapperException $e) {
            throw new SchoolboxException('User Does not Exist', 404, null, 404);
        }
        //preload parents / children for the user
        $this->getRelationshipHydrator()->loadChildren([$user]);
        $this->getRelationshipHydrator()->loadParents([$user]);

        return [
            'user' => $user,
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

    /**
     * @return GroupMapper
     */
    protected function getGroupMapper(): GroupMapper
    {
        return $this->getMapperFactory()->get(GroupMapper::class);
    }

    /**
     * Quick call to build the external user relationship hydrator
     *
     * @return \Schoolbox\External\User
     */
    protected function getRelationshipHydrator()
    {
        return new \Schoolbox\External\User(
            $this->getConfig()->get('external_type'),
            new \External_Class(),
            new \External_User(),
            $this->getUserMapper()
        );
    }
}
