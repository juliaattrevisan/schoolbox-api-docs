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
use Alaress\Web\Controller\Base;
use Aura\Router\Map;
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
    public function initRoutes(string $basePath)
    {
        $this->attachResourceRoutes($basePath);
        $this->addRoute('search', $basePath, '/search');
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
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                '(?:read)',
                new Rule\All(
                    new Rule\IsLoggedIn(),
                    new Rule\Any(
                        new Auth\CanReadItem(),
                        new Auth\CanModerateItem(),
                        new Rule\IsSuperuser(),
                        new Rule\HasServicePermission(UserAccess::SERVICE_RESOURCE_BOOKING, UserAccess::ACCESS_FULL),
                    )
                )
            )
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return (new Controller())
            ->setMapper($this->getMapperFactory()->get(Mapper::class))
            ->setUserMapper($this->getMapperFactory()->get(UserMapper::class))
            ->setRoleMapper($this->getMapperFactory()->get(RoleMapper::class))
            ->setTermMapper($this->getMapperFactory()->get(TermMapper::class))
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
