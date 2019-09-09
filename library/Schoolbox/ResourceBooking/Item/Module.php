<?php
/**
 * Module definition for the resource booking item module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Paginator\UrlPaginator;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Alaress\Twig\Loader\IChainLoader;
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
        $this->addRoute($router, 'read', $basePath, '/{id}');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Add the item to params, if ID is in the route.
        $this->addRoutePreload(
            $matcher,
            '.*',
            function($params) {
                if (array_key_exists('id', $params)) {
                    return array(
                        'item' => $this->getMapper()->findById($params['id']),
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
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector) {
        $injector->setters[Controller::class]['setMapper']
            = $this->getMapperFactory()->getLazy(Mapper::class);
        $injector->setters[Controller::class]['setUserMapper']
            = $this->getMapperFactory()->getLazy(UserMapper::class);
        $injector->setters[Controller::class]['setRoleMapper']
            = $this->getMapperFactory()->getLazy(RoleMapper::class);
        $injector->setters[Controller::class]['setTermMapper']
            = $this->getMapperFactory()->getLazy(TermMapper::class);
        // Paginator needs the count per page and amount set.
        $injector->setters[Controller::class]['setPaginator']
            = $injector->lazyNew(
                UrlPaginator::class,
                array(),
                array(
                    'setPageSize' => 10,
                    'setParam'    => 'page',
                )
        );
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class)
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper
     */
    protected function getMapper()
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }

}
