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
        Helper::FEATURE_CHAT            => ConfigMapper::NAME_AI_TEXT_CHAT,
        Helper::FEATURE_LESSONPLAN      => ConfigMapper::NAME_AI_LESSONPLAN,
        Helper::FEATURE_DIFFERENTIATION => ConfigMapper::NAME_AI_DIFFERENTIATION,
        Helper::FEATURE_DIALOGAGENT     => ConfigMapper::NAME_AI_DIALOGAGENT,
        Helper::FEATURE_IMAGE           => ConfigMapper::NAME_AI_IMAGE_GENERATION,
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

    public const OUTPUT_QUALITY_OPTIONS = [
        'high',
        'medium',
        'low',
        'auto',
    ];

    // field name and types
    public const string FIELD_PROVIDER = 'provider';
    public const string FIELD_AIKEY = 'ai_key';
    public const string FIELD_RESOURCE_NAME = 'resource_name';
    public const string FIELD_DEPLOYMENT_ID = 'deployment_id';
    public const string FIELD_MODEL = 'model';
    public const string FIELD_SYSTEM_PROMPT  = 'system_prompt';
    public const string FIELD_OUTPUT_QUALITY = 'output_quality';


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

    public const SECTION_GENERAL = 'general';
    public const SECTION_OPENAI = 'OpenAI';
    public const SECTION_AZURE = 'Azure';

    /**
     * Which form inputs exist for each AI provider?
     */
    public const DEFAULT_FORM_INPUTS = [
        self::FIELD_PROVIDER      => [
            'type'        => 'select',
            'title'       => 'Provider',
            'description' => 'Select which provider you will use for this AI service; choose "Off" to disable this feature.',
            'options'     => self::PROVIDERS,
            'section'     => null,
        ],
        self::FIELD_AIKEY         => [
            'type'        => 'input',
            'title'       => 'OpenAI API Key',
            'description' => 'This is your secure key for authorising GPT based AI assistant services. The key can be created at https://openai.com/ or in Azure.',
            'section'     => self::SECTION_GENERAL,
        ],
        self::FIELD_MODEL         => [
            'type'        => 'select',
            'title'       => 'OpenAI Model',
            'description' => 'Select which model you wish to use for your OpenAI service.',
            'options'     => self::AI_MODELS,
            'providers'   => [Helper::ENDPOINT_TYPE_OPENAI,],
            'section'     => self::SECTION_OPENAI,
        ],
        self::FIELD_RESOURCE_NAME => [
            'type'        => 'input',
            'title'       => 'Azure OpenAI Resource Name',
            'description' => 'The name of your Azure OpenAI Resource.',
            'section'     => self::SECTION_AZURE,
        ],
        self::FIELD_DEPLOYMENT_ID => [
            'type'        => 'input',
            'title'       => 'Azure OpenAI Deployment ID',
            'description' => 'The deployment name you chose when you deployed the model in Azure.',
            'section'     => self::SECTION_AZURE,
        ],
        self::FIELD_SYSTEM_PROMPT => [
            'type'        => 'textarea',
            'title'       => 'System Prompt',
            'description' => 'Provide some additional instructions to shape what the AI service generates.',
            'section'     => self::SECTION_GENERAL,
        ],
    ];

    /**
     * array of AI features and their config
     * @var array|null
     */
    private ?array $features = null;

    /**
     * Returns the general fields for an AI feature.
     *
     * @param string $feature The feature to fetch for
     * @return array
     */
    public function initGeneral(string $feature): array
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
     * Returns the OpenAI-specific fields for an AI feature.
     *
     * @param string $feature The feature to fetch for
     * @return array[]
     */
    public function initOpenAi(string $feature): array
    {
        return [
            self::FIELD_MODEL => [
                'value' => '', // -- select an option --
            ],
        ];
    }

    /**
     * Returns the Azure-specific fields for an AI feature.
     *
     * @param string $feature The feature to fetch for
     * @return array[]
     */
    public function initAzure(string $feature): array
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

            // TODO set which options are allowed: e.g. provider options, fields
            foreach ($rows as $row) {
                $feature = array_search($row->getName(), self::ALL_MODULES, true);
                $this->features[$feature] = [
                    'feature' => $feature,
                    'title' => $row->getTitle(),
                    'config' => $this->decode($row->getValue()),
                    'formInputs' => $this->buildFormInputs($feature),
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
     *       system_prompt: array{value: string},
     *       output_quality?: array{value: string},
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
     * @param array  $config
     * @param string $feature The feature whose config is being encoded
     * @return false|string
     * @throws \JsonException
     * @throws SchoolboxException // invalid config
     */
    public function encode(array $config, string $feature): false|string
    {
        $formInputs     = $this->buildFormInputs($feature);
        $validProviders = $formInputs[self::FIELD_PROVIDER]['options'];

        // ensure the provider is one of the allowed one
        if (
            !in_array($config[self::FIELD_PROVIDER]['value'] ?? null, $validProviders, true)
        ) {
            throw new SchoolboxException("Invalid provider specified in config");
        }

        // Build a blank out of the form inputs
        $blank = [];
        foreach ($formInputs as $name => $formInput) {
            $section = $formInput['section'];
            match ($section) {
                self::SECTION_GENERAL,  self::SECTION_OPENAI, self::SECTION_AZURE => $blank[$section][$name]['value'] = '',
                default => $blank[$name]['value'] = '',
            };
        }

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

    /**
     * Build form inputs for this feature.
     *
     * @param string $feature
     * @return array[]
     */
    private function buildFormInputs(string $feature): array
    {
        $formInputs = self::DEFAULT_FORM_INPUTS;
        if ($feature === Helper::FEATURE_IMAGE) {
            // Image feature:
            // * add output quality
            // * remove Azure option
            $formInputs[self::FIELD_OUTPUT_QUALITY] = [
                'type'        => 'select',
                'title'       => 'Output Quality',
                'description' => 'Select which output quality you wish to use for image generation. A higher quality will improve the result of more complex images but will take longer to generate and use more credit.',
                'options'     => self::OUTPUT_QUALITY_OPTIONS,
                'section'     => self::SECTION_OPENAI,
            ];
            unset($formInputs[self::FIELD_PROVIDER]['options'][Helper::ENDPOINT_TYPE_AZURE]);
        }
        return $formInputs;
    }
}
