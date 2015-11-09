<?php
/**
 * Module definition for the resource booking item module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\ResourceBooking\Item;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Failure;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use Schoolbox\Permission\Access\UserAccess;

class Module extends BaseModule
{
    protected $basename = 'resourceItem';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->attachResourceRoutes($router, $basePath);
        $this->addRoute($router, 'search', $basePath, '/search');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher) {
        // Add the item to params, if ID is in the route.
        $this->addRoutePreload(
            $matcher,
            '.*',
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
                    new Rule\HasServicePermission(UserAccess::SERVICE_RESOURCE_BOOKING, UserAccess::ACCESS_FULL),
                    new Auth\CanModerateItem()
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
        $injector->setters['Schoolbox\\ResourceBooking\\Item\\Controller']['setMapper']
            = $injector->lazyNew('Schoolbox\\ResourceBooking\\Item\\Mapper');
        $injector->setters['Schoolbox\\ResourceBooking\\Item\\Controller']['setUserMapper']
            = $injector->lazyNew('Schoolbox\\UserMapper');
        $injector->setters['Schoolbox\\ResourceBooking\\Item\\Controller']['setRoleMapper']
            = $injector->lazyNew('Schoolbox\\RoleMapper');
        // Paginator needs the count per page and amount set.
        $injector->setters['Schoolbox\\ResourceBooking\\Item\\Controller']['setPaginator']
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
            $injector->lazyNew('Schoolbox\\ResourceBooking\\Item\\Controller')
        );
    }

}
