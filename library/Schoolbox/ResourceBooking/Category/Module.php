<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\ResourceBooking\Category;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Twig_Loader_Filesystem;
use Schoolbox\UserAccess;

class Module extends BaseModule {

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath) {
        $router->attachResource('resourceCategory', $basePath);
        $router->attachResourcePostAlias('resourceCategory', $basePath);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {
        $resourceManageRule = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasServicePermission(UserAccess::SERVICE_RESOURCE_BOOKING, UserAccess::ACCESS_FULL)
        );
        $authorizer
            ->addRule('/^resourceCategory(?:$|\.)/',                 new Rule\IsLoggedIn())
            ->addRule('/^resourceCategory\.(?:create|edit|delete)/', $resourceManageRule)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Filesystem $templateLoader) {
        $templateLoader->addPath(__DIR__.'/templates', 'resourceCategory');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector) {
        $injector->setters['Schoolbox\\ResourceBooking\\Category\\Controller']['setMapper']
            = $injector->lazyNew('Schoolbox\\ResourceBooking\\Category\\Mapper');
        $injector->set(
            'controller:resourceCategory',
            $injector->lazyNew('Schoolbox\\ResourceBooking\\Category\\Controller')
        );
    }

}
