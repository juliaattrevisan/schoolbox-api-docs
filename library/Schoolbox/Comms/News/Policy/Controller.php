<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Policy;

use Alaress\Web\Controller\Base;
use Config;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\News\TopicMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;

class Controller extends Base
{

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var TopicMapper */
    private $topicMapper;

    /** @var ConfigMapper */
    private $configMapper;

    /** @var FieldStrategyFactory */
    private $fieldRendererFactory;


    /**
     * @param ConfigMapper         $configMapper
     * @param FieldStrategyFactory $fieldRendererFactory
     * @param UserAccessMapper     $userAccessMapper
     * @param TopicMapper          $topicMapper
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldRendererFactory,
        UserAccessMapper $userAccessMapper,
        TopicMapper $topicMapper
    ) {
        $this->configMapper         = $configMapper;
        $this->fieldRendererFactory = $fieldRendererFactory;
        $this->userAccessMapper     = $userAccessMapper;
        $this->topicMapper          = $topicMapper;
    }


    /**
     * Provide form
     *
     * @api GET /comms/news/policy
     *
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getPolicies(): void
    {
        $configContext = (new ModuleConfig($this->fieldRendererFactory))->getContext(
            $this->getConfigLoader()->get('site_version'),
            $this->configMapper->findByColumn(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_COMMS),
            $this->router->generate('commsNewsPolicy.postPolicies')
        );

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsPolicy/index.twig', [
            'pageCurrent'   => 'news',
            'commsOfficers' => $this->userAccessMapper->findUsersForService(UserAccess::SERVICE_COMMS_OFFICER),
            'configContext' => $configContext,
            'topics'        => $this->topicMapper->findAll(['order' => ['sequence' => 'asc']]),
        ])->setTitle('Comms Policies'));
    }


    /**
     * Save policy settings
     *
     * @api POST /comms/news/policy
     *
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postPolicies(): void
    {
        /** @var GenericCollection|Config[] $existingConfigs */
        $existingConfigs = $this->configMapper->findByColumn(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_COMMS)->indexBy(ConfigMapper::COL_NAME);

        foreach ($this->request->post->get() as $name => $value) {
            $existingConfig  = $existingConfigs[$name];
            $valueSerializer = $this->fieldRendererFactory->makeValueSerializer($existingConfig->getFieldId());
            $newValue        = $valueSerializer->serializeValue($value);

            if ($newValue !== $existingConfig->getValue()) {
                $existingConfig->setValue($newValue);
                $this->configMapper->save($existingConfig);
            }
        }

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('commsNewsPolicy.getPolicies'));
    }
}
