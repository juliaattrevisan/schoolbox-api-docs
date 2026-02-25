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
        Helper::FEATURE_CHAT                            => ConfigMapper::NAME_AI_TEXT_CHAT,
        Helper::FEATURE_LESSONPLAN                      => ConfigMapper::NAME_AI_LESSONPLAN,
        Helper::FEATURE_DIFFERENTIATION                 => ConfigMapper::NAME_AI_DIFFERENTIATION,
        Helper::FEATURE_DIALOGAGENT                     => ConfigMapper::NAME_AI_DIALOGAGENT,
        Helper::FEATURE_IMAGE                           => ConfigMapper::NAME_AI_IMAGE_GENERATION,
        Helper::FEATURE_LEARNING_ACTIVITY_CUSTOMISATION => ConfigMapper::NAME_AI_LEARNING_ACTIVITY_CUSTOMISATION,
        Helper::FEATURE_AI_MODERATION_AGENT             => ConfigMapper::NAME_AI_MODERATION_AGENT,
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
        "gpt-5",
        "gpt-5-mini",
        "gpt-5-nano",
        "gpt-5.1",
        "gpt-5.2",
        "gpt-5.2-pro",
    ];

    public const string IMAGE_MODEL_DEFAULT = 'gpt-image-1';
    public const array AI_IMAGE_MODELS = [
        "gpt-image-1",
        "gpt-image-1.5",
    ];

    public const string OUTPUT_QUALITY_DEFAULT = 'low';
    public const array OUTPUT_QUALITY_OPTIONS = [
        'high',
        'medium',
        'low',
        'auto',
    ];

    public const REASONING_EFFORT_MODEL_DEFAULTS = [
        "gpt-4o"       => 'none',
        "gpt-4o-mini"  => 'none',
        "gpt-4.1"      => 'none',
        "gpt-4.1-mini" => 'none',
        "gpt-5"        => 'minimal',
        "gpt-5-mini"   => 'minimal',
        "gpt-5-nano"   => 'minimal',
        "gpt-5.1"      => 'none',
        "gpt-5.2"      => 'none',
        "gpt-5.2-pro"  => 'high',
    ];
    public const array REASONING_EFFORT_MODEL_OPTIONS = [
        "gpt-4o"       => ['none'],
        "gpt-4o-mini"  => ['none'],
        "gpt-4.1"      => ['none'],
        "gpt-4.1-mini" => ['none'],
        "gpt-5"        => ['minimal', 'low', 'medium', 'high'],
        "gpt-5-mini"   => ['minimal', 'low', 'medium', 'high'],
        "gpt-5-nano"   => ['minimal', 'low', 'medium', 'high'],
        "gpt-5.1"      => ['none', 'low', 'medium', 'high'],
        "gpt-5.2"      => ['none', 'low', 'medium', 'high'],
        "gpt-5.2-pro"  => ['high'],
    ];
    public const array REASONING_EFFORT_OPTIONS = [
        'none',
        'minimal',
        'low',
        'medium',
        'high',
    ];

    public const string TEXT_VERBOSITY_DEFAULT = 'low';
    public const array TEXT_VERBOSITY_OPTIONS = [
        'low',
        'medium',
        'high',
    ];

    // field keys
    public const string FIELD_PROVIDER = 'provider';
    public const string FIELD_AIKEY = 'ai_key';
    public const string FIELD_RESOURCE_NAME = 'resource_name';
    public const string FIELD_DEPLOYMENT_ID = 'deployment_id';
    public const string FIELD_MODEL = 'model';
    public const string FIELD_AI_MODERATION = 'ai_moderation';
    public const string FIELD_SYSTEM_PROMPT  = 'system_prompt';
    public const string FIELD_OUTPUT_QUALITY = 'output_quality';
    public const string FIELD_IMAGE_MODEL = 'image_model';
    public const string FIELD_REASONING_EFFORT = 'reasoning_effort';
    public const string FIELD_TEXT_VERBOSITY = 'text_verbosity';

    // field types
    public const string FIELD_TYPE_SELECT   = 'select';
    public const string FIELD_TYPE_INPUT    = 'input';
    public const string FIELD_TYPE_TEXTAREA = 'textarea';
    public const string FIELD_TYPE_CHECKBOX = 'checkbox';


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
        self::FIELD_REASONING_EFFORT => [
            'value' => '',
        ],
        self::FIELD_TEXT_VERBOSITY => [
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

    public const string SECTION_GENERAL = 'general';
    public const string SECTION_OPENAI = 'OpenAI';
    public const string SECTION_AZURE = 'Azure';

    /**
     * Which form inputs exist for each AI provider?
     */
    public const array DEFAULT_FORM_INPUTS = [
        self::FIELD_PROVIDER      => [
            'type'    => self::FIELD_TYPE_SELECT,
            'options' => self::PROVIDERS,
            'section' => null,
        ],
        self::FIELD_AIKEY         => [
            'type'    => self::FIELD_TYPE_INPUT,
            'section' => self::SECTION_GENERAL,
        ],
        self::FIELD_MODEL         => [
            'type'    => self::FIELD_TYPE_SELECT,
            'options' => self::AI_MODELS,
            'section' => self::SECTION_OPENAI,
        ],
        self::FIELD_RESOURCE_NAME => [
            'type'    => self::FIELD_TYPE_INPUT,
            'section' => self::SECTION_AZURE,
        ],
        self::FIELD_DEPLOYMENT_ID => [
            'type'    => self::FIELD_TYPE_INPUT,
            'section' => self::SECTION_AZURE,
        ],
        self::FIELD_SYSTEM_PROMPT => [
            'type'    => self::FIELD_TYPE_TEXTAREA,
            'section' => self::SECTION_GENERAL,
        ],
        self::FIELD_REASONING_EFFORT => [
            'type'    => self::FIELD_TYPE_SELECT,
            'options' => self::REASONING_EFFORT_OPTIONS,
            'section' => self::SECTION_GENERAL,
        ],
        self::FIELD_TEXT_VERBOSITY => [
            'type'    => self::FIELD_TYPE_SELECT,
            'options' => self::TEXT_VERBOSITY_OPTIONS,
            'section' => self::SECTION_GENERAL,
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
        return $this->initFields($feature, self::SECTION_GENERAL);
    }

    /**
     * Returns the OpenAI-specific fields for an AI feature.
     *
     * @param string $feature The feature to fetch for
     * @return array[]
     */
    public function initOpenAi(string $feature): array
    {
        return $this->initFields($feature, self::SECTION_OPENAI);
    }

    /**
     * Returns the Azure-specific fields for an AI feature.
     *
     * @param string $feature The feature to fetch for
     * @return array[]
     */
    public function initAzure(string $feature): array
    {
        return $this->initFields($feature, self::SECTION_AZURE);
    }

    /**
     * Returns the fields for a particular section of a feature.
     *
     * @param string $feature The feature to fetch for
     * @param string $section The section whose fields to fetch
     * @return array
     */
    private function initFields(string $feature, string $section): array
    {
        $fields = [];
        foreach ($this->buildFormInputs($feature) as $name => $formInput) {
            if (!isset($formInput['section'])) {
                continue;
            }
            if ($formInput['section'] === $section) {
                $fields[$name] = ['value' => ''];
            }
        }
        return $fields;
    }

    /**
     * Decide if $feature is one of the ai modules we support
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

        return array_key_exists($feature, self::ALL_MODULES);
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
        return $config[self::FIELD_PROVIDER]['value'] !== Helper::ENDPOINT_TYPE_OFF;
    }

    /**
     * Check if the feature has AI moderation enabled
     *
     * @param array $config
     * @return bool
     */
    public function isAiModerationEnabled(array $config): bool
    {
        // TODO: there's no way in this current setup to know from $config what feature it's actually for?
        // For now just check if the field is set and truthy...
        $value = $config[self::SECTION_GENERAL][self::FIELD_AI_MODERATION]['value'] ?? false;
        return (bool)$value;
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
                self::SECTION_GENERAL, self::SECTION_OPENAI, self::SECTION_AZURE => $blank[$section][$name]['value'] = '',
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
        if (\in_array($feature, Helper::FEATURES_WITH_AI_MODERATION, true)) {
            // Any feature with AI moderation support gets a config for it
            $formInputs[self::FIELD_AI_MODERATION] = [
                'type'    => self::FIELD_TYPE_CHECKBOX,
                'section' => self::SECTION_GENERAL,
            ];
        }

        if ($feature === Helper::FEATURE_IMAGE) {
            // Image feature:
            // * add output quality and image model
            // * remove Azure option
            $formInputs[self::FIELD_OUTPUT_QUALITY] = [
                'type'    => self::FIELD_TYPE_SELECT,
                'options' => self::OUTPUT_QUALITY_OPTIONS,
                'section' => self::SECTION_OPENAI,
            ];
            $formInputs[self::FIELD_IMAGE_MODEL] = [
                'type'    => self::FIELD_TYPE_SELECT,
                'options' => self::AI_IMAGE_MODELS,
                'section' => self::SECTION_OPENAI,
            ];
            unset($formInputs[self::FIELD_PROVIDER]['options'][Helper::ENDPOINT_TYPE_AZURE]);
        }

        if ($feature === Helper::FEATURE_AI_MODERATION_AGENT) {
            // Ai Moderation agent:
            // * remove generation system prompt
            unset($formInputs[self::FIELD_SYSTEM_PROMPT]);
        }

        return $formInputs;
    }
}
