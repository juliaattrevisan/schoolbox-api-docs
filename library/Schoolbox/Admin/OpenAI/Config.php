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
    public const array ALL_MODULES = [
        Helper::FEATURE_CHAT => ConfigMapper::NAME_AI_TEXT_CHAT,
        Helper::FEATURE_LESSONPLAN => ConfigMapper::NAME_AI_LESSONPLAN,
        Helper::FEATURE_DIFFERENTIATION => ConfigMapper::NAME_AI_DIFFERENTIATION,
        Helper::FEATURE_DIALOGAGENT => ConfigMapper::NAME_AI_DIALOGAGENT,
    ];

    public const array PROVIDERS = [
        Helper::ENDPOINT_TYPE_OFF => Helper::ENDPOINT_TYPE_OFF,
        Helper::ENDPOINT_TYPE_OPENAI => Helper::ENDPOINT_TYPE_OPENAI,
        Helper::ENDPOINT_TYPE_AZURE => Helper::ENDPOINT_TYPE_AZURE,
    ];

    public const array AI_MODELS = [
        "gpt-4o",
        "gpt-4o-mini",
        "gpt-4.1",
        "gpt-4.1-mini",
    ];

    // field name and types
    public const string FIELD_PROVIDER = 'provider';
    public const string FIELD_AIKEY = 'ai_key';
    public const string FIELD_RESOURCE_NAME = 'resource_name';
    public const string FIELD_DEPLOYMENT_ID = 'deployment_id';
    public const string FIELD_MODEL = 'model';
    public const string FIELD_SYSTEM_PROMPT = 'system_prompt';


    /* Predefined config structs: as you add more providers, don't forget to pile them in here
     */

    /**
     * Common fields across all providers
     */
    public const array STRUCT_GENERAL = [
        self::FIELD_AIKEY => [
            'value' => '',
        ],
        self::FIELD_SYSTEM_PROMPT => [
            'value' => '',
        ],
    ];


    /**
     * OpenAI-specific fields
     */
    public const array STRUCT_OPENAI = [
        self::FIELD_MODEL => [
            'value' => '', // -- select an option --
        ],
    ];


    /**
     * Azure-specific fields
     */
    public const array STRUCT_AZURE = [
        self::FIELD_RESOURCE_NAME => [
            'value' => '',
        ],
        self::FIELD_DEPLOYMENT_ID => [
            'value' => '',
        ],
    ];


    /**
     * A set of all available fields to show in the ui
     */
    public const array FIELDS = [
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


    /**
     * Decide if $feature is one of the ai modules we support
     *
     * Note that atm the Image Generator is treated specially which we need to repair at next opportunity.
     *
     * @param null|string $feature
     * @return bool
     */
    public function hasFeature(?string $feature): bool
    {
        /* Exit early, skipping all that postprocessing if we're doomed to failure
         */
        if (null === $feature) {
            return false;
        }

        return array_key_exists($feature, array_merge(self::ALL_MODULES, ['image']));
    }


    /**
     * returns a blank config structure
     * @return array
     */
    private function getBlank(): array
    {
        // start with a blank config
        $config = [self::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OFF]];
        $config[Helper::ENDPOINT_TYPE_GENERAL] = self::STRUCT_GENERAL;
        $config[Helper::ENDPOINT_TYPE_OPENAI] = self::STRUCT_OPENAI;
        $config[Helper::ENDPOINT_TYPE_AZURE] = self::STRUCT_AZURE;
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
     * Get the model or deployment ID from the config
     *
     * @param array|null $config
     * @return string|null
     */
    public function getModel(?array $config): ?string
    {
        if ($config === null) {
            return null;
        }
        /**
         * Retrieve the model to use in requests
         * OpenAI lets us choose the model (from config),
         * Azure has the model linked to the deployment ID when you set it up over there, so we can't choose it
         */
        if ($config[self::FIELD_PROVIDER] === Helper::ENDPOINT_TYPE_OPENAI) {
            return $config[Helper::ENDPOINT_TYPE_OPENAI][self::FIELD_MODEL]['value'] ?? null;
        }

        if ($config[self::FIELD_PROVIDER] === Helper::ENDPOINT_TYPE_AZURE) {
            return $config[Helper::ENDPOINT_TYPE_AZURE][self::FIELD_DEPLOYMENT_ID]['value'] ?? null;
        }
        return null;
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
            $rows = $configMapper->findByColumn(ConfigMapper::COL_NAME, self::ALL_MODULES, [
                'order' => [
                    'sequence' => 'asc'
                ]
            ]);

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
        foreach (self::PROVIDERS as $provider => $configName) {
            // skip checking 'Off' provider
            if (Helper::ENDPOINT_TYPE_OFF === $provider) {
                /* TODO this ignores inspection of the payload if you'd set it to "off"
                 *  it could be totally misshapen by the time you restore it, maybe
                 */
                continue;
            }

            // find any missing fields in the config for this module
            if (count(array_diff_key($blank[$provider], $config[$provider] ?? [])) > 0) {
                throw new SchoolboxException("Invalid config structure for provider '$provider', missing fields");
            }

            // find any extra fields in the config for this module
            if (count(array_diff_key($config[$provider] ?? [], $blank[$provider])) > 0) {
                throw new SchoolboxException("Invalid config structure for provider '$provider', extra fields found");
            }
        }

        // config is valid, encode to json
        return json_encode($config, JSON_THROW_ON_ERROR);
    }
}
