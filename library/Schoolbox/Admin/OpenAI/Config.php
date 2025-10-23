<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI;

use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as SchoolboxException;

class Config
{
    /**
     * enabled AI features (to be configured as part of the text Gen settings)
     * feature name => config name
     */
    public const ALL_MODULES = [
        Helper::FEATURE_CHAT => ConfigMapper::NAME_AI_TEXT_CHAT,
        Helper::FEATURE_LESSONPLAN => ConfigMapper::NAME_AI_LESSONPLAN,
        Helper::FEATURE_DIFFERENTIATION => ConfigMapper::NAME_AI_DIFFERENTIATION,
        Helper::FEATURE_DIALOGAGENT => ConfigMapper::NAME_AI_DIALOGAGENT,
    ];

    public const PROVIDERS = [
        Helper::ENDPOINT_TYPE_OFF => Helper::ENDPOINT_TYPE_OFF,
        Helper::ENDPOINT_TYPE_OPENAI => Helper::ENDPOINT_TYPE_OPENAI,
        Helper::ENDPOINT_TYPE_AZURE => Helper::ENDPOINT_TYPE_AZURE,
    ];

    /** Open AI models, and the 'OFF' option to be used in the same select box */
    public const NOT_ACTIVE = "Not Active";



    public const AI_MODELS = [
        "gpt-4o",
        "gpt-4o-mini",
        "gpt-4.1",
        "gpt-4.1-mini",
    ];

    // field name and types
    public const FIELD_PROVIDER = 'provider';
    public const FIELD_AIKEY = 'ai_key';
    public const FIELD_RESOURCE_NAME = 'resource_name';
    public const FIELD_DEPLOYMENT_ID = 'deployment_id';
    public const FIELD_MODEL = 'model';
    public const FIELD_SYSTEM_PROMPT = 'system_prompt';

    public const FIELDS = [
        self::FIELD_PROVIDER => self::FIELD_PROVIDER,
        self::FIELD_AIKEY => self::FIELD_AIKEY,
        self::FIELD_RESOURCE_NAME => self::FIELD_RESOURCE_NAME,
        self::FIELD_DEPLOYMENT_ID => self::FIELD_DEPLOYMENT_ID,
        self::FIELD_MODEL => self::FIELD_MODEL,
        self::FIELD_SYSTEM_PROMPT => self::FIELD_SYSTEM_PROMPT,
    ];

    /**
     * array of AI features and their config
     * @var array|null
     */
    private ?array $features = null;

    // new config struct
    /**
     *
     * $aiChat = [
     * 'provider' => 'off' // can be off openai or azure if OFF then feature is disabled
     * 'openai' => [
     * 'key' => [ // should this be in a common space?
     * 'value' => 'aaaaaa',
     * ],
     * 'model' => [
     * 'value' => 'gippity4.1-mini',
     * ],
     * 'systemprompt' => [ // should this be in a common space?
     * 'value' => 'Talk like a pirate',
     * ],
     * 'azure' => [
     * 'resource' => [
     * 'value' => 'ccccccccc',
     * ],
     * 'deployment' => [
     * 'value' => 'dddddd',
     * ]
     * ]
     * ];
     *
     */

    /**
     * returns the common fields for all AI features
     * @return array
     */
    public function initCommon()
    {
        return [
            self::FIELD_AIKEY => [
                'value' => '',
            ],
            self::FIELD_SYSTEM_PROMPT => [
                'value' => '',
            ],
        ];
    }

    /**
     * returns the OpenAI specific fields
     * @return array[]
     */
    public function initOpenAi()
    {
        return [
            self::FIELD_MODEL => [
                'value' => '', // -- select an option --
            ],
        ];
    }

    /**
     * returns the Azure specific fields
     * @return array[]
     */
    public function initAzure()
    {

        return [
            self::FIELD_RESOURCE_NAME => [
                'value' => '',
            ],
            self::FIELD_DEPLOYMENT_ID => [
                'value' => '',
            ],
        ];
    }

    /**
     * returns a blank config structure
     * @return array
     */
    private function getBlank()
    {
        // start with a blank config
        $config = [self::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OFF]];
        $config[Helper::ENDPOINT_TYPE_GENERAL] = $this->initCommon();
        $config[Helper::ENDPOINT_TYPE_OPENAI] = $this->initOpenAi();
        $config[Helper::ENDPOINT_TYPE_AZURE] = $this->initAzure();
        return $config;
    }

    /**
     * Check if the config is enabled
     *
     * @param array|null $config
     * @return bool
     */
    public function isEnabled(?array $config): bool
    {
        // if no config, then not enabled
        if ($config === null) {
            return false;
        }
        return $config[self::FIELD_PROVIDER] !== Helper::ENDPOINT_TYPE_OFF;
    }

    /**
     * Fetch all features and their config from the DB
     * @param ConfigMapper $configMapper
     * @return array
     * @throws \JsonException
     */
    public function fetchFeatures(ConfigMapper $configMapper): array
    {
        if ($this->features === null) {
            // hit the db once, get all module configs for AI features, then build the array
            $rows = $configMapper->findByColumn(ConfigMapper::COL_NAME, self::ALL_MODULES);
            foreach ($rows as $row) {
                $feature = array_search($row->getName(), self::ALL_MODULES, true);
                $this->features[$feature] = [
                    'feature' => $feature,
                    'title' => $row->getTitle(),
                    'config' => $this->decode($row->getValue())

                ];
            }
        }

        return $this->features;
    }

    /**
     * @param string|null $config
     * @return array{
     *    provider: array{value: string},
     *     general: array{
     *       ai_key: array{value: string},
     *       system_prompt: array{value: string}
     *     },
     *    OpenAI: array{
     *      model: array{value: string},
     *    },
     *    Azure: array{
     *      resource_name: array{value: string},
     *      deployment_id: array{value: string}
     *    }
     *  }|null
     * @throws \JsonException
     */
    public function decode(?string $config): ?array
    {
        if ($config === null) {
            return null;
        }
        return json_decode($config, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Encode function for config array
     * @param array $config
     * @return false|string
     * @throws \JsonException
     * @throws SchoolboxException // invalid config
     */
    public function encode(array $config): false|string
    {
        // ensure the provider is one of the allowed one
        if (
            !in_array($config[self::FIELD_PROVIDER]['value'] ?? null, self::PROVIDERS, true)
        ) {
            throw new SchoolboxException("Invalid provider specified in config");
        }

        $blank = $this->getBlank();
        foreach (self::PROVIDERS as $module => $configName) {
            // skip checking 'Off' provider
            if (Helper::ENDPOINT_TYPE_OFF === $module) {
                continue;
            }
            //array combine with blank config to ensure all fields are present
            $fullConfig = array_merge($blank[$module], $config[$module] ?? []);

            // remove any extra fields that are not in the blank config
            if (count(array_diff_key($fullConfig, $config[$module] ?? [])) > 0) {
                throw new SchoolboxException("Invalid config structure for feature '$module'");
            }
        }

        // config is valid, encode to json
        return json_encode($config, JSON_THROW_ON_ERROR);
    }
}
