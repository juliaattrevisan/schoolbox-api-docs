<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\User;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Map;
use Aura\Router\Router;
use HouseMapper;
use HouseResidentialMapper;
use Schoolbox\CampusMapper;
use Schoolbox\CMS\Log\Mapper as CMSLogMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User\Title\Mapper as UserTitleMapper;
use Schoolbox\User\Pronoun;
use Schoolbox\User\Log\Mapper as UserLogMapper;
use Schoolbox\User\Token\Mapper as TokenMapper;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel;

/**
 * The module for managing school users through the admin section.
 *
 * @package Schoolbox\Admin\Users
 */
class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'adminUser';

    /**
     * @inheritdoc
     */
    public function initRoutes(Map $routeMap, $basePath)
    {
        $this->addGetRoute($routeMap, 'getInsert', $basePath, "/create");
        $this->addGetRoute($routeMap, 'getModify', $basePath, "/{userId}/modify")->setTokens(['userId' => '\d+']);
        $this->addGetRoute($routeMap, 'getStaleReport', $basePath, "/stale");
        $this->addGetRoute($routeMap, 'getStaleUsers', $basePath, "/stale/list");


        // CRUD Actions

        $this->addPostRoute($routeMap, 'postDetails', $basePath, "{/userId}");
        $this->addPostRoute($routeMap, 'postInsertGroup', $basePath, "/{userId}/group");
        $this->addPostRoute($routeMap, 'postInsertPermission', $basePath, "/{userId}/permission");
        $this->addPostRoute($routeMap, 'postInsertLinked', $basePath, "/{userId}/linked");

        $this->addPostRoute($routeMap, 'postUpdateGroup', $basePath, "/{userId}/group/{folderId}");
        $this->addPostRoute($routeMap, 'postUpdateService', $basePath, "/{userId}/permission/{service}/{sid}");

        $this->addPostRoute($routeMap, 'postDeleteList', $basePath, "/list/delete");
        $this->addPostRoute($routeMap, 'postDeleteUser', $basePath, "/{userId}/delete");
        $this->addPostRoute($routeMap, 'postDeletePermission', $basePath, "/{userId}/permission/{service}/{sid}/delete");
        $this->addPostRoute($routeMap, 'postDeleteGroup', $basePath, "/{userId}/group/{folderId}/delete");
        $this->addPostRoute($routeMap, 'postDeleteLinked', $basePath, "/{userId}/linked/{linkedId}/delete");

        // Other stuff

        $this->addPostRoute($routeMap, 'postAlert', $basePath, "/{userId}/alert");
    }

    /**
     * @inheritdoc
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser())
        ;
    }

    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this
            ->addRoutePreload($matcher, '.*', function ($params) {

                if (array_key_exists('userId', $params)) {
                    if (null === $params['userId']) { // When posting from the create user form, userId is null, we should create a new user for here
                        $params['user'] = $this->getUserMapper()->createModel();
                        return $params; // creating new user, exit early
                    }
                    $params['user'] = $this->getUserMapper()->findById($params['userId']);
                }

                if (array_key_exists('folderId', $params)) {
                    $params['folder'] = $this->getFolderMapper()->findById($params['folderId']);
                }

                if (array_key_exists('linkedId', $params)) {
                    $params['linked'] = $this->getUserMapper()->findById($params['linkedId']);
                }

                return $params;
            })
        ;
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(CMSLogMapper::class),
            $this->getMapperFactory()->get(UserLogMapper::class),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(UserAccessMapper::class),
            $this->getMapperFactory()->get(UserTitleMapper::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(HouseResidentialMapper::class),
            $this->getMapperFactory()->get(HouseMapper::class),
            $this->getMapperFactory()->get(TokenMapper::class),
            $this->getMapperFactory()->get(YearLevel\Mapper::class),
            $this->getMapperFactory()->get(Pronoun\Mapper::class),
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
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
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }
}
