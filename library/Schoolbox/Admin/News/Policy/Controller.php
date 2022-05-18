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
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
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
     * @param UserAccessMapper     $userAccessMapper
     * @param Topic\Mapper         $topicMapper
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        UserAccessMapper $userAccessMapper,
        Topic\Mapper $topicMapper
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder);

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
            $this->router->generate('commsNewsPolicy.postPolicies'),
            [ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false,],
        );

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsPolicy/index.twig', [
            'pageCurrent'   => $this->router->generate('commsNewsPolicy.getPolicies'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('commsNewsPolicy.getPolicies')),
            'commsOfficers' => $this->userAccessMapper->findUsersForService(UserAccess::SERVICE_NEWS, null, UserAccess::ACCESS_FULL)->indexBy('id'),
            'configContext' => $configContext,
            'topics'        => $this->topicMapper->findAll(['order' => ['sequence' => 'asc']]),
        ])->setTitle('Comms Policies'));
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
        $this->updateConfig($this->request->post->get(), $this->buildConfigPredicate());
        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('commsNewsPolicy.getPolicies'));
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
