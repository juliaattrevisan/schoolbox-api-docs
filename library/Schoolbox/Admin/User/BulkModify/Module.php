<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\User\BulkModify;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use HouseMapper;
use Schoolbox\CampusMapper;
use Schoolbox\RoleMapper;
use Schoolbox\UserBulkMapper;
use Schoolbox\YearLevel;

/**
 * The module for bulk modify users through the admin section.
 *
 * @package Schoolbox\Admin\Users\BulkModify
 */
class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'adminUserBulkModify';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getModify', $basePath, "/modify");
        $this->addPostRoute($router, 'getUserList', $basePath, "/modify/list");
        $this->addPostRoute($router, 'postModify', $basePath, "/modifyForm");
        $this->addPostRoute($router, 'deleteUsers', $basePath, "/modifyForm");
    }


//    /**
//     * Hook method for setting up routes via self::register()
//     *
//     * @inheritdoc
//     */
//    protected function registerRoutes(): void
//    {
//        /* Only superusers can access the Groups API
//         */
//        $hasApiAccess = new Rule\All(
//            new Rule\IsLoggedIn(),
//            new Rule\IsSuperuser()
//        );
//
//        $this->register('DELETE', '/modifyForm', 'deleteUsers', $hasApiAccess);
//    }

    /**
     * @inheritdoc
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('subscriptionCache'),
            $this->getMapperFactory()->get(UserBulkMapper::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(HouseMapper::class),
            $this->getMapperFactory()->get(YearLevel\Mapper::class)
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
