<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Admin;

use Alaress\Web;
use Config;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Alaress\Database\Predicate;

class Controller extends Web\Controller\Base
{

    /** @var ConfigMapper */
    private $configMapper;

    /** @var FieldStrategyFactory */
    private $fieldStrategyFactory;


    /**
     * @param ConfigMapper         $configMapper
     * @param FieldStrategyFactory $fieldStrategyFactory
     */
    public function __construct(ConfigMapper $configMapper, FieldStrategyFactory $fieldStrategyFactory)
    {
        $this->configMapper         = $configMapper;
        $this->fieldStrategyFactory = $fieldStrategyFactory;
    }


    /**
     * Renders the module admin page
     *
     * @api   GET /adminv2/custom/tigs/summative
     * @level 18.1.0
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getAdmin(): void
    {
        $pb = $this->buildModuleConfigPredicate();

        $configContext = (new ModuleConfig($this->fieldStrategyFactory))->getContext(
            $this->getConfigLoader()->get('site_version'),
            $this->configMapper->findByPredicates($pb, ['order' => [ConfigMapper::COL_ID => 'ASC']]),
            $this->router->generate('tigsSummativeAdmin.postAdmin')
        );

        $this
            ->getResponse()->content
            ->set($this->buildHtmlOutput('@tigsSummativeAdmin/admin.twig', [
                'configContext' => $configContext,
            ])
                       ->setTitle("TIGS Summative Reporting Admin"));
    }


    /**
     * Updates admin items available on the module admin page
     *
     * @api   POST /adminv2/custom/tigs/summative
     * @level 18.1.0
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws Predicate\InvalidColumnException
     */
    public function postAdmin(): void
    {
        /** @var GenericCollection|Config[] $existingConfigs */
        $existingConfigs = $this->configMapper->findByPredicates($this->buildModuleConfigPredicate())->indexBy(ConfigMapper::COL_NAME);

        foreach ($this->request->post->get() as $name => $value) {
            $existingConfig  = $existingConfigs[$name];
            $valueSerializer = $this->fieldStrategyFactory->makeValueSerializer($existingConfig->getFieldId());
            $newValue        = $valueSerializer->serializeValue($value);

            if ($newValue !== $existingConfig->getValue()) {
                $existingConfig->setValue($newValue);
                $this->configMapper->save($existingConfig);
            }
        }

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('tigsSummativeAdmin.getAdmin'));
    }


    /**
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return \Alaress\Database\Predicate\Builder
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    private function buildModuleConfigPredicate(): Predicate\Builder
    {
        return $this->configMapper->getPredicateBuilder()
                                  ->like(ConfigMapper::COL_NAME, ConfigMapper::PREFIX_TIGS_CRP . "%")
                                  ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_CUSTOM);
    }
}
