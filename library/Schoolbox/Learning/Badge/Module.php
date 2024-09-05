<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Rule\HasServicePermission;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Closure;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\Filter;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Role;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;

/**
 * Module for badge requests.
 */
class Module extends BaseModule
{
    protected $basename = 'learningBadge';


    /**
     * @inheritdoc
     */
    public function registerRoutes(): void
    {
        /*
         * Users with `write` badge service user permission
         */
        $permissionBadgeModeratorWrite =
            new HasServicePermission(
                UserAccess::SERVICE_BADGE,
                UserAccess::ACCESS_WRITE
            );

        /*
         * Users with `moderate` badge service user permission
         */
        $permissionBadgeModeratorModerate =
            new HasServicePermission(
                UserAccess::SERVICE_BADGE,
                UserAccess::ACCESS_FULL
            );

        //TODO: will need to change to allow users with 'Set Goal', 'Award' or 'Modify' badge permissions
        //TODO: also include users with 'write' and 'moderate' badge service user permissions
        $viewBadgesRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
            ),
        );

        $writeBadgesRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                $permissionBadgeModeratorWrite
            ),
        );

        //TODO: this will need to be changed to allow moderator, creator and users with moderate badge permissions
        $manageBadgesRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRolePermission(Role::PERMISSION_CREATE_BADGES, Role::STAFF),
            ),
        );

        /* Get a JSON feed of badges that are available in the instance
         */
        $this->register(
            self::METHOD_GET,
            '/list',
            'getBadgeJsonList',
            new Rule\IsLoggedIn()
        );

        /* Get a Badge
         */
        $this->register(
            self::METHOD_GET,
            '/{badgeId:\d+}',
            'getBadge',
            new Rule\IsLoggedIn(),
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        // HTML view of a list of badges
        $this->register(
            self::METHOD_GET,
            '',
            'getBadgeListPage',
            $viewBadgesRule
        );

        /* The form that people use to Create a Badge
         */
        $this->register(
            self::METHOD_GET,
            '/create',
            'getCreateForm',
            $writeBadgesRule
        );

        /* The form that people use to Update a Badge
         */
        $this->register(
            self::METHOD_GET,
            '/{badgeId}/update',
            'getUpdateForm',
            $manageBadgesRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        /* Update a Badge
         */
        $this->register(
            self::METHOD_POST,
            '/{badgeId}/update',
            'postUpdate',
            $manageBadgesRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        /* Delete a Badge
         */
        $this->register(
            self::METHOD_DELETE,
            '/{badgeId}',
            'deleteBadge',
            $manageBadgesRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        /* Create a Badge
         */
        $this->register(
            self::METHOD_POST,
            '',
            'postBadge',
            $writeBadgesRule
        );
    }


    /**
     * Preload the requested badge
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function preloadBadge(array $params): array
    {
        return [
            'badge' => $this->getMapperFactory()
                ->get(Mapper::class)
                ->findById($params['badgeId']),
        ];
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->injector->get('filters.factoryFactory'),
            $this->getMapperFactory()->get(Filter\Mapper::class),
            $this->getMapperFactory()->get(Filter\Selection\Mapper::class),
            $this->getMapperFactory()->get(StorageMapper::class),
            $this->getMapperFactory()->get(Category\Mapper::class),
            $this->getMapperFactory()->get(YearLevelMapper::class),
            $this->getMapperFactory()->get(CurriculumNodeMapper::class),
        );
    }
}
