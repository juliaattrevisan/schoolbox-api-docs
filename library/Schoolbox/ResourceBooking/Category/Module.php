<?php
/**
 * Module definition for the resource booking category module.
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
use Alaress\Web\Router\PathMatcher;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use Schoolbox\Permission\Access\UserAccess;

class Module extends BaseModule
{
    protected $basename = 'resourceCategory';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->attachResourceRoutes($router, $basePath);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher) {
        // Add the item to params, if ID is in the route.
        $this->addRoutePreload(
            $matcher,
            'resourceCategory',
            function($params) {
                if (array_key_exists('id', $params)) {
                    return array(
                        'item' => Mapper::getInstance()->findById($params['id']),
                    );
                }
                return array();
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                '(?:add|edit|delete)',
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_RESOURCE_BOOKING, UserAccess::ACCESS_FULL)
                )
            );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector) {
        $injector->setters['Schoolbox\\ResourceBooking\\Category\\Controller']['setMapper']
            = $injector->lazyNew('Schoolbox\\ResourceBooking\\Category\\Mapper');
        $injector->setters['Schoolbox\\ResourceBooking\\Category\\Controller']['setPaginator']
            = $injector->lazyNew(
            'Alaress\\Paginator\\UrlPaginator',
            array(),
            array(
                'setPageSize' => 10,
                'setParam'    => 'page',
            )
        );
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew('Schoolbox\\ResourceBooking\\Category\\Controller')
        );
    }

}
