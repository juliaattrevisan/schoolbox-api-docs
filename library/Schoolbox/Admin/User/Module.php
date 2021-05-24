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
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
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
use Schoolbox\User\Log\Mapper as UserLogMapper;
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
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getInsert', $basePath, "/create");
        $this->addGetRoute($router, 'getModify', $basePath, "/{userId}/modify")->setTokens(['userId' => '\d+']);
        $this->addGetRoute($router, 'getStaleReport', $basePath, "/stale");
        $this->addGetRoute($router, 'getStaleUsers', $basePath, "/stale/list");


        // CRUD Actions

        $this->addPostRoute($router, 'postDetails', $basePath, "{/userId}");
        $this->addPostRoute($router, 'postInsertGroup', $basePath, "/{userId}/group");
        $this->addPostRoute($router, 'postInsertPermission', $basePath, "/{userId}/permission");
        $this->addPostRoute($router, 'postInsertLinked', $basePath, "/{userId}/linked");

        $this->addPostRoute($router, 'postUpdateGroup', $basePath, "/{userId}/group/{folderId}");
        $this->addPostRoute($router, 'postUpdateService', $basePath, "/{userId}/permission/{service}/{sid}");

        $this->addPostRoute($router, 'postDeleteList', $basePath, "/list/delete");
        $this->addPostRoute($router, 'postDeleteUser', $basePath, "/{userId}/delete");
        $this->addPostRoute($router, 'postDeletePermission', $basePath, "/{userId}/permission/{service}/{sid}/delete");
        $this->addPostRoute($router, 'postDeleteGroup', $basePath, "/{userId}/group/{folderId}/delete");
        $this->addPostRoute($router, 'postDeleteLinked', $basePath, "/{userId}/linked/{linkedId}/delete");

        // Other stuff

        $this->addPostRoute($router, 'postAlert', $basePath, "/{userId}/alert");
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
                    $params['user'] = $this->getUserMapper()->findByIdWithFallback($params['userId']);
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
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'folderMapper' => $this->getMapperFactory()->getLazy(FolderMapper::class),
                    'userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class),
                    'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                    'campusMapper' => $this->getMapperFactory()->getLazy(CampusMapper::class),
                    'houseMapper' => $this->getMapperFactory()->getLazy(HouseMapper::class),
                    'houseResidentialMapper' => $this->getMapperFactory()->getLazy(HouseResidentialMapper::class),
                    'userTitleMapper' => $this->getMapperFactory()->getLazy(UserTitleMapper::class),
                    'userLogMapper' => $this->getMapperFactory()->getLazy(UserLogMapper::class),
                    'cmsLogMapper' => $this->getMapperFactory()->getLazy(CMSLogMapper::class),
                    'userAccessMapper' => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
                    'roleMapper' => $this->getMapperFactory()->getLazy(RoleMapper::class),
                    'tokenBuilder' => $injector->get('user.tokenBuilder'),
                    'yearLevelMapper' => $this->getMapperFactory()->getLazy(YearLevel\Mapper::class),
                ]
            )
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
