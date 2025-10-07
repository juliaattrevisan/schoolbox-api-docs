<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI;

use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\GenericCollection;
use Schoolbox\Config\ConfigMapper;

class Config
{
    // enabled AI features (to be configured as part of the text Gen settings)
    public const ALL_MODULES = [
        Helper::FEATURE_CHAT,
        Helper::FEATURE_LESSONPLAN,
//        Helper::FEATURE_DIFFERENTIATION,
//        Helper::FEATURE_ASSESSMENT,
    ];

    public const MODULE_CONFIG = [
        Helper::FEATURE_CHAT => ConfigMapper::NAME_OPENAI_TEXT_CHAT,
        Helper::FEATURE_LESSONPLAN => ConfigMapper::NAME_OPENAI_LESSONPLAN,
        Helper::FEATURE_DIFFERENTIATION => ConfigMapper::NAME_OPENAI_ASSESSMENT,
        Helper::FEATURE_ASSESSMENT => ConfigMapper::NAME_OPENAI_DIFFERENTIATION,
    ];


    // field name and types
    public const FIELD_PROVIDER = 'provider';
    public const FIELD_ENABLED = 'enabled';
    public const FIELD_OPENAIKEY = 'open_ai_key';
    public const FIELD_RESOURCE_NAME = 'resource_name';
    public const FIELD_DEPLOYMENT_ID = 'deployment_id';
    public const FIELD_MODEL = 'model';
    // model options
    public const FIELD_OPTIONS = [
        self::FIELD_PROVIDER => ConfigMapper::FIELD_INPUT,
        self::FIELD_ENABLED => ConfigMapper::FIELD_BOOLEAN,
        self::FIELD_OPENAIKEY => ConfigMapper::FIELD_INPUT,
        self::FIELD_RESOURCE_NAME => ConfigMapper::FIELD_INPUT,
        self::FIELD_DEPLOYMENT_ID => ConfigMapper::FIELD_INPUT,
        self::FIELD_MODEL => ConfigMapper::FIELD_SELECT,
    ];

    // human readable titles
    public const FIELD_TITLE = [
        self::FIELD_PROVIDER => 'Endpoint Provider',
        self::FIELD_ENABLED => 'Enable Feature',
        self::FIELD_OPENAIKEY => 'OpenAI API Key',
        self::FIELD_RESOURCE_NAME => 'Azure Resource Name',
        self::FIELD_DEPLOYMENT_ID => 'Azure Deployment ID',
        self::FIELD_MODEL => 'OpenAI Model',
    ];

    public const NOT_ACTIVE = "Not Active";
    public const AI_MODELS = [
        self::NOT_ACTIVE,
        "gpt-4o",
        "gpt-4o-mini",
        "gpt-4.1",
        "gpt-4.1-mini",
    ];

    public const CONFIG_STRUCT_AZURE = [
        self::FIELD_PROVIDER => Helper::ENDPOINT_TYPE_AZURE,
        self::FIELD_ENABLED => false,
        self::FIELD_OPENAIKEY => '',
        self::FIELD_RESOURCE_NAME => '',
        self::FIELD_DEPLOYMENT_ID => '',
    ];

    public const CONFIG_STRUCT_OPENAI = [
        self::FIELD_PROVIDER => Helper::ENDPOINT_TYPE_OPENAI,
        self::FIELD_OPENAIKEY => '',
        self::FIELD_MODEL => self::NOT_ACTIVE,
    ];

    /**
     * Take the stored settings and convert it into an associative array
     *
     * @param string|null $config
     * @param string $provider
     * @return array|null
     * @throws \JsonException
     */
    public function decode(?string $config, string $provider): ?array
    {
        $optionsPayload = json_decode($config ?? '{}', true, 512, JSON_THROW_ON_ERROR);

        /* Superimpose selected options over the default
         * Disabling fields prevents the keys from existing
         */
        return self::mergeConfig($optionsPayload, $provider);
    }


    /**
     * Generate initial config structure based on provider
     *
     * @param string $provider
     * @return array
     */
    public static function generateInitConfig(string $provider): array
    {
        return match ($provider) {
            Helper::ENDPOINT_TYPE_AZURE => self::CONFIG_STRUCT_AZURE,
            Helper::ENDPOINT_TYPE_OPENAI => self::CONFIG_STRUCT_OPENAI,
            default => [],
        };
    }

    /**
     * Merge config with initial and remove any invalid keys,
     * overwriting any existing values.
     *
     * @param array $config
     * @param string $provider
     * @return array
     */
    public static function mergeConfig(array $config, string $provider): array
    {
        switch ($provider) {
            case Helper::ENDPOINT_TYPE_AZURE:
                $expected = self::CONFIG_STRUCT_AZURE;
                break;
            case Helper::ENDPOINT_TYPE_OPENAI:
                $expected = self::CONFIG_STRUCT_OPENAI;
                break;
            default:
                return [];
        }

//        $merged = [];
//        foreach (self::ALL_MODULES as $module) {
            // Get existing config for this module if it exists
            $moduleConfig = isset($config) && is_array($config)
                ? $config
                : [];

            // Only keep expected keys and overwrite defaults
            $merged = array_intersect_key($moduleConfig, $expected) + $expected;

            // Always set provider to avoid issues if switching providers
            $merged['provider'] = $provider;
//        }

        return $merged;
    }

    /**
     * Build the standard Twig config for setting page for all modules
     * @param array $config
     * @param $fieldStrategyFactory
     * @param $siteVersion
     * @param $postRoute
     * @param $additionalContext
     * @return array|void
     */
    public function buildTwigConfig(array $config, $fieldStrategyFactory, $siteVersion, $postRoute, $additionalContext)
    {
        //loop through the modules
        if (empty($config)) {
            return;
        }
        $modules = [];
        foreach ($config as $module => $settings) {
            // create a fake config collection
            $configCollection = new GenericCollection(\Schoolbox\Config\Config::class);
            foreach ($settings as $key => $value) {
                $cfg = new \Schoolbox\Config\Config();
                $cfg->setName(sprintf("[%s][%s]", $module, $key))
                    ->setTitle(self::FIELD_TITLE[$key])
                    ->setDescription('hello')
                    ->setValue((string)$value)
                    ->setTypeId(ConfigMapper::TYPE_AI)
                    ->setFieldId(self::FIELD_OPTIONS[$key] ?? ConfigMapper::FIELD_INPUT)
                    ->setEditable(true);

                // if model, show options
                if ($key === self::FIELD_MODEL) {
                    $cfg->setOptions(implode(':', self::AI_MODELS));
                }

                // don't show provider
                if ($key === self::FIELD_PROVIDER) {
                    $cfg->setEditable(false);
//                        ->setHidden(true);
                }
                $configCollection->add($cfg);
            }

            $modules[$module] = (new ModuleConfig($fieldStrategyFactory))->getContext(
                $siteVersion,
                $configCollection,
                $postRoute,
                $additionalContext,
            );
        }

        return $modules;
    }

    /**
     * migrate from the old config to the new config structure
     *
     * @param string|null $provider
     * @param string|null $key
     * @param string|null $resourceName
     * @param string|null $deploymentId
     * @param string|null $model
     * @return array
     */
    public static function migrate(?string $provider, ?string $key, ?string $resourceName, ?string $deploymentId, ?string $model): array
    {
        switch ($provider) {
            case Helper::ENDPOINT_TYPE_AZURE:
                $config = self::generateInitConfig($provider);
                $config[self::FIELD_OPENAIKEY] = $key ?? '';
                $config[self::FIELD_RESOURCE_NAME] = $resourceName ?? '';
                $config[self::FIELD_DEPLOYMENT_ID] = $deploymentId ?? '';
                $config[self::FIELD_ENABLED] = true;
                break;
            case Helper::ENDPOINT_TYPE_OPENAI:
                $config = self::generateInitConfig($provider);
                $config[self::FIELD_OPENAIKEY] = $key ?? '';
                $config[self::FIELD_MODEL] = $model ?? self::NOT_ACTIVE;
                $config[self::FIELD_ENABLED] = true;

                break;
            default:
                $config = [];
        }
        return $config;
    }

    /**
     * check if the config is enabled
     * both openai and azure do this differently
     */
    public static function isEnabled(?array $config): bool
    {
        if (empty($config) || !isset($config['provider'])) {
            return false;
        }
        return match ($config['provider']) {
            Helper::ENDPOINT_TYPE_AZURE => $config['enabled'],
            Helper::ENDPOINT_TYPE_OPENAI => $config['model'] !== self::NOT_ACTIVE,
            default => false,
        };
    }
}
