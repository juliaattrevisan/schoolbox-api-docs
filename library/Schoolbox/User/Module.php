<?php

/**
 * Module definition for userspace actions
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Router;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Groups\GroupsHelper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;
use User\Logger;

class Module extends BaseModule
{
    protected $basename = 'user';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes()
    {
        $folderPermFull = new Rule\HasHomepageAccess(UserAccess::ACCESS_FULL, function (array $params = array(), User $user = null) {
            return $params['folderId'];
        }, Access::getInstance());

        $loggedIn = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn()
            );
        });

        $hasApiAccess = new Rule\IsSuperuser();

        $preloadUser = function ($params) {
            return $this->preloadUser($params);
        };

        $preloadUserGroup = function ($params) {
            return $this->preloadUserGroup($params);
        };

        $this->register('GET', '/parentEmails/folder/{folderId}', 'getParentEmailsByFolder', $folderPermFull);
        $this->register('GET', '/search', 'search', $loggedIn);
        $this->register('GET', '/{userId}/groupMembership', 'apiMembership', $hasApiAccess, $preloadUser);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'impersonate', $basePath, '/switch/{userId}');
        $this->addGetRoute($router, 'recoverSession', $basePath, '/recoverSession');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $folderMapper = $this->getFolderMapper();
        $errorMapper = $this->getErrorMapper();

        $this->addRoutePreload(
            $matcher,
            'impersonate',
            function ($params) {
                return array(
                    'user' => $this->getUserMapper()->findById($params['userId']),
                );
            }
        );

        // Convert folderId into Folder object
        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) use ($folderMapper, $errorMapper) {

                if (array_key_exists('folderId', $params)) {
                    try {
                        $params['folder'] = $folderMapper->findById($params['folderId']);
                    } catch (MapperException $e) {
                        throw $errorMapper->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                    }
                }

                return $params;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '(impersonate|recoverSession)', new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                'impersonate',
                new Rule\Any(
                    new Rule\Callback(
                        function ($params, User $user) {
                            // Do not allow unlinked users to switch
                            foreach ($user->getLinkedUser() as $linkedUser) {
                                if ($params['userId'] == $linkedUser->getId()) {
                                    return true;
                                }
                            }
                            throw new Unauthorized();
                        }
                    ),
                    new Rule\IsSuperuser()
                )
            );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(UserAccessMapper::class),
            $this->injector->get('externalUser'),
            $this->getMapperFactory()->get(Logger\Mapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(GroupMapper::class),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(Access::class),
            $this->getMapperFactory()->get(\Timetable_ColourMapper::class),
            $this->getMapperFactory()->get(RerouteOverrideMapper::class),
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
            throw new SchoolboxException('User Does not Exist');
        }

        return [
            'user' => $user,
        ];
    }

    /**
     * Preload a User and Group instances
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws SchoolboxException
     */
    private function preloadUserGroup(array $params): array
    {
        $user = $this->preloadUser($params);

        try {
            $group = $this->getGroupMapper()->findOneByColumn('cid', $params['groupId']);
        } catch (MapperException $e) {
            throw new SchoolboxException('Group Does not Exist');
        }

        return [
            'user' => $user,
            'group' => $group,
        ];
    }

    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return GroupMapper
     */
    protected function getGroupMapper(): GroupMapper
    {
        return $this->getMapperFactory()->get(GroupMapper::class);
    }
}
