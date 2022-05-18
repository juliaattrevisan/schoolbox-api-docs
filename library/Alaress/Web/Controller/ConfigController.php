<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Controller;

use Alaress\Database\Predicate;
use Alaress\Module\Cache\Rebuilder;
use Closure;
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
     * Module/route cache rebuilder: required as changing config settings may
     * change the modules and routes available.
     */
    protected Rebuilder $rebuilder;

    /**
     * Constructor
     *
     * @param ConfigMapper         $configMapper
     * @param FieldStrategyFactory $fieldStrategyFactory
     * @param Rebuilder            $rebuilder
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder
    ) {
        $this->configMapper         = $configMapper;
        $this->fieldStrategyFactory = $fieldStrategyFactory;
        $this->rebuilder            = $rebuilder;
    }

    /**
     * Returns data suitable for injecting into standard config twig template
     *
     * @param Predicate\Builder $pb                A PB for finding the config for this module
     * @param string            $postRoute         Where should the config be posted to
     * @param array             $additionalContext Any additional context to add
     * @return array
     */
    protected function buildConfigContext(
        Predicate\Builder $pb,
        string $postRoute,
        array $additionalContext = []
    ): array {
        return (new ModuleConfig($this->fieldStrategyFactory))->getContext(
            $this->getConfigLoader()->get('site_version'),
            $this->configMapper->findByPredicates($pb, [
                'order' => [
                    'sequence' => 'ASC',
                    ConfigMapper::COL_ID => 'ASC'
                ]
            ]),
            $postRoute,
            $additionalContext,
        );
    }


    /**
     * Saves posted config to database.
     *
     * Also triggers a rebuild of routes if a setting which affects routes has
     * changed.
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

        $rebuildModules = false;
        foreach ($post as $name => $value) {
            if (!$existingConfigs->keyExists($name)) {
                // Ignore posted config not for this module
                continue;
            }
            $existingConfig  = $existingConfigs[$name];
            $valueSerializer = $this->fieldStrategyFactory->makeValueSerializer($existingConfig->getFieldId());
            $newValue        = $valueSerializer->serializeValue($value);

            if ($newValue !== $existingConfig->getValue()) {
                $existingConfig->setValue($newValue);
                $this->configMapper->save($existingConfig);
                $rebuildModules = $rebuildModules || in_array($name, ConfigMapper::SETTINGS_AFFECTING_MODULES);
            }
        }

        if ($rebuildModules) {
            $this->response->content->set([
                'format'     => 'noContent',
                'postOutput' => [
                    $this->buildRebuildModuleCacheCallback(),
                ],
            ]);
        }
    }


    /**
     * Create a callback for rebuilding the module cache, suitable for use as a
     * `postOutput` callback.
     *
     * @return Closure
     */
    protected function buildRebuildModuleCacheCallback(): Closure
    {
        return Closure::fromCallable(
            function () {
                $this->rebuilder->rebuild($this->getConfigLoader()->get(ConfigMapper::NAME_SITE_SCHOOL_CODE));
            }
        );
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
