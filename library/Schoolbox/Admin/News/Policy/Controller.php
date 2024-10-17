<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\News\Policy;

use Alaress\Database\Predicate;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Comms\News\Topic\Mapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Comms\News\Topic;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;

class Controller extends ConfigController
{
    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var Topic\Mapper */
    private $topicMapper;


    /**
     * Constructor
     *
     * @param ConfigMapper         $configMapper
     * @param FieldStrategyFactory $fieldStrategyFactory
     * @param Rebuilder            $rebuilder
     * @param CacheInterface       $oidcCache
     * @param UserAccessMapper     $userAccessMapper
     * @param Mapper               $topicMapper
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
        UserAccessMapper $userAccessMapper,
        Topic\Mapper $topicMapper
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);

        $this->userAccessMapper = $userAccessMapper;
        $this->topicMapper      = $topicMapper;
    }


    /**
     * Provide form
     *
     * @api GET /news/policy
     *
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getPolicies(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->routeGenerator->generate('commsNewsPolicy.postPolicies'),
            [ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false,],
        );

        $this->response = $this->responseFromTemplate(
            '@commsNewsPolicy/index.twig',
            [
                'pageCurrent'   => $this->routeGenerator->generate('commsNewsPolicy.getPolicies'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('commsNewsPolicy.getPolicies')),
                'commsOfficers' => $this->userAccessMapper->findUsersForService(UserAccess::SERVICE_NEWS, null, UserAccess::ACCESS_FULL)->indexBy('id'),
                'configContext' => $configContext,
                'topics'        => $this->topicMapper->findAll(['order' => ['sequence' => 'asc']]),
            ],
            'Comms Policies'
        );
    }


    /**
     * Save policy settings
     *
     * @api POST /news/policy
     *
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws Predicate\InvalidColumnException
     */
    public function postPolicies(): void
    {
        $this->updateConfig($this->requestPost->getArrayCopy(), $this->buildConfigPredicate());
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('commsNewsPolicy.getPolicies'));
    }


    /**
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return \Alaress\Database\Predicate\Builder
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    private function buildConfigPredicate(): Predicate\Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_COMMS);
    }
}
