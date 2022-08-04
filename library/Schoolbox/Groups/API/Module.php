<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIGroup;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'groupApi';


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

        $preloadUser = function ($params) {
            return [
                'user' => $this->preloadUser($params)
                ];
        };

        $preloadUserGroup = function ($params) {
            return [
                'user' => $this->preloadUser($params),
                'group' => $this->preloadGroup($params)
                ];
        };

        $this->register('GET', '/{userId}/group', 'apiMembership', $hasApiAccess, $preloadUser);
        $this->register('POST', '/{userId}/group/{groupId}', 'apiMembershipAdd', $hasApiAccess, $preloadUserGroup);
        $this->register('GET', '/{userId}/group/{groupId}', 'apiMembershipGet', $hasApiAccess, $preloadUserGroup);
        $this->register('PUT', '/{userId}/group/{groupId}', 'apiMembershipPut', $hasApiAccess, $preloadUserGroup);
        $this->register('PATCH', '/{userId}/group/{groupId}', 'apiMembershipPatch', $hasApiAccess, $preloadUserGroup);
        $this->register('DELETE', '/{userId}/group/{groupId}', 'apiMembershipDelete', $hasApiAccess, $preloadUserGroup);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(UserAccessMapper::class),
            $this->getMapperFactory()->get(GroupMapper::class),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(\Timetable_ColourMapper::class),
            $this->getMapperFactory()->get(RerouteOverrideMapper::class),
        );
    }

    /**
     * Preload a User instance
     *
     * @param array $params
     * @return object
     * @throws \InvalidArgumentException
     * @throws SchoolboxException
     */
    private function preloadUser(array $params): object
    {
        try {
            $user = $this->getUserMapper()->findById($params['userId']);
        } catch (MapperException $e) {
            throw new SchoolboxException('User Does not Exist', 404, null, 404);
        }

        return $user;
    }

    /**
     * Preload a Group instances
     *
     * @param array $params
     * @return object
     * @throws \InvalidArgumentException
     * @throws SchoolboxException
     */
    private function preloadGroup(array $params): object
    {
        try {
            $folder = $this->getFolderMapper()->findById($params['groupId']);
            $group = $folder->getGroup();
            if (!$group) {
                throw new SchoolboxException('Group Does not Exist', 404, null, 404);
            }
        } catch (MapperException $e) {
            throw new SchoolboxException('Group Does not Exist', 404, null, 404);
        }
        return $group;
    }

    /**
     * @return UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper(): UserMapper
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper(): FolderMapper
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }
}
