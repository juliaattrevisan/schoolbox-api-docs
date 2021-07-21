<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Closure;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\Filter;
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
        $viewBadgesRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
            ),
        );

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
            $manageBadgesRule
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

        // Create a badge
        /* Delete a Badge
         */
        $this->register(
            self::METHOD_DELETE,
            '/{badgeId}',
            'deleteBadge',
            $manageBadgesRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        $this->register(
            self::METHOD_POST,
            '',
            'postBadge',
            $manageBadgesRule
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
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'mapper'                => $this->getMapperFactory()->getLazy(Mapper::class),
                    'filterFactoryFactory'  => $injector->lazyGet('filters.factoryFactory'),
                    'filterMapper'          => $this->getMapperFactory()->getLazy(Filter\Mapper::class),
                    'filterSelectionMapper' => $this->getMapperFactory()->getLazy(Filter\Selection\Mapper::class),
                    'storageMapper'         => $this->getMapperFactory()->getLazy(StorageMapper::class),
                    'categoryMapper'        => $this->getMapperFactory()->getLazy(Category\Mapper::class),
                    'yearLevelMapper'       => $this->getMapperFactory()->getLazy(YearLevelMapper::class),
                    'curriculumNodeMapper'  => $this->getMapperFactory()->getLazy(CurriculumNodeMapper::class),
                ]
            )
        );
    }
}
