<?php

/**
 * Module definition for the resource booking category module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Category;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Paginator\UrlPaginator;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ResourceBooking;

class Module extends BaseModule
{
    protected $basename = 'resourceCategory';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->attachResourceRoutes($router, $basePath);
        $this->addGetRoute($router, 'calendar', $basePath, '/{id}/calendar');
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
            function ($params) {
                if (array_key_exists('id', $params)) {
                    return [
                        'category' => $this->getMapper()->findById($params['id']),
                    ];
                }
                return [];
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
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
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return (new Controller($this->getMapperFactory()->get(ResourceBooking\Item\Mapper::class)))
            ->setMapper($this->getMapperFactory()->get(Mapper::class))
            ->setPaginator(
                (new UrlPaginator())
                    ->setPaginator(new \Alaress\Paginator\Paginator())
                    ->setPageSize(10)
                    ->setParam('page')
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
