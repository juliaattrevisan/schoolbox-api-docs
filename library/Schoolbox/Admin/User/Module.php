<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\User;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use HouseMapper;
use HouseResidentialMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserMapper;
use Schoolbox\User\Title\Mapper as UserTitleMapper;
use Schoolbox\User\Log\Mapper as UserLogMapper;
use Schoolbox\CMS\Log\Mapper as CMSLogMapper;
use Schoolbox\CampusMapper;
use Twig_Loader_Chain;

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
        $this->addGetRoute($router, 'getModify', $basePath, "/modify/{userId}");
        $this->addGetRoute($router, 'getDeleteGroups', $basePath, "/delete/groups/{userId}/{folderId}");
        $this->addGetRoute($router, 'getDeleteLinked', $basePath, "/delete/linked/{userId}/{linkedId}");

        $this->addPostRoute($router, 'postModifyDetails', $basePath, "/modify/details");
        $this->addPostRoute($router, 'postModifyGroups', $basePath, "/modify/groups");
        $this->addPostRoute($router, 'postModifyPermissions', $basePath, "/modify/permissions");
        $this->addPostRoute($router, 'postModifyLinked', $basePath, "/modify/linked");

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
            ->addRoutePreload($matcher, '.*', function($params) {
                $returnParams = [];

                if (array_key_exists('userId', $params)) {
                    $returnParams['user'] = $this->getUserMapper()->findById($params['userId']);
                }

                if (array_key_exists('folderId', $params)) {
                    $returnParams['folder'] = $this->getFolderMapper()->findById($params['folderId']);
                }

                if (array_key_exists('linkedId', $params)) {
                    $returnParams['linked'] = $this->getFolderMapper()->findById($params['linkedId']);
                }

                return $returnParams;
            })
        ;
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'folderMapper' => $this->getMapperFactory()->getLazy(FolderMapper::class),
                    'userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class),
                    'campusMapper' => $this->getMapperFactory()->getLazy(CampusMapper::class),
                    'houseMapper' => $this->getMapperFactory()->getLazy(HouseMapper::class),
                    'houseResidentialMapper' => $this->getMapperFactory()->getLazy(HouseResidentialMapper::class),
                    'userTitleMapper' => $this->getMapperFactory()->getLazy(UserTitleMapper::class),
                    'userLogMapper' => $this->getMapperFactory()->getLazy(UserLogMapper::class),
                    'cmsLogMapper' => $this->getMapperFactory()->getLazy(CMSLogMapper::class),
                ]
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
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
