<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Web\Controller;

use Alaress\Database\Predicate;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;

/**
 * Abstract base class for module configs
 */
abstract class ConfigController extends Base
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
     * Returns data suitable for injecting into standard config twig template
     *
     * @param Predicate\Builder $pb
     * @param string            $route
     * @return array
     */
    protected function buildConfigContext(Predicate\Builder $pb, string $route): array
    {
        return (new ModuleConfig($this->fieldStrategyFactory))->getContext(
            $this->getConfigLoader()->get('site_version'),
            $this->configMapper->findByPredicates($pb, ['order' => [ConfigMapper::COL_ID => 'ASC']]),
            $route
        );
    }


    /**
     * Saves posted config to database
     *
     * @param array             $post
     * @param Predicate\Builder $pb
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function updateConfig(array $post, Predicate\Builder $pb): void
    {
        /** @var GenericCollection|Config[] $existingConfigs */
        $existingConfigs = $this->configMapper->findByPredicates($pb)->indexBy(ConfigMapper::COL_NAME);

        foreach ($post as $name => $value) {
            if(!\array_key_exists($name, $existingConfigs)){
                // Ignore posted config not for this module
                continue;
            }
            $existingConfig  = $existingConfigs[$name];
            $valueSerializer = $this->fieldStrategyFactory->makeValueSerializer($existingConfig->getFieldId());
            $newValue        = $valueSerializer->serializeValue($value);

            if ($newValue !== $existingConfig->getValue()) {
                $existingConfig->setValue($newValue);
                $this->configMapper->save($existingConfig);
            }
        }
    }


    /**
     * @return ConfigMapper
     */
    protected function getConfigMapper(): ConfigMapper
    {
        return $this->configMapper;
    }


    /**
     * @return FieldStrategyFactory
     */
    protected function getFieldStrategyFactory(): FieldStrategyFactory
    {
        return $this->fieldStrategyFactory;
    }
}
