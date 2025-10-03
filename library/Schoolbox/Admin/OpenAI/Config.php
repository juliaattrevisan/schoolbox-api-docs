<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI;

class Config
{
    // CKEditor chat
    public const MODULE_TEXTCHAT = 'textChat';
    // Lesson Plan Generator
    public const MODULE_LESSONPLAN = 'lessonPlan';
    public const MODULE_AIASSESSMENT = 'aiAssessment';

    public const ALL_MODULES = [
        self::MODULE_TEXTCHAT,
        self::MODULE_LESSONPLAN,
//        self::MODULE_AIASSESSMENT,
    ];

    public const NOT_ACTIVE = "Not Active";
    public const AI_MODELS = [
        self::NOT_ACTIVE,
        "gpt-4o",
        "gpt-4o-mini",
        "gpt-4.1",
        "gpt-4.1-mini",
    ];

    public const PROVIDER_AZURE = 'Azure';
    public const PROVIDER_OPENAI = 'OpenAI';

    public const CONFIG_STRUCT_AZURE =
        [
            'provider' => self::PROVIDER_AZURE,
            'enabled' => false,
            'open_ai_key' => '',
            'resource_name' => '',
            'deployment_id' => '',
        ];

    public const CONFIG_STRUCT_OPENAI =
        [
            'provider' => self::PROVIDER_OPENAI,
            'open_ai_key' => '',
            'model' => self::NOT_ACTIVE,
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
        $optionsPayload = json_decode($config, true, 512, JSON_THROW_ON_ERROR);

        /* Bad data or couldn't decode, for some obscure reason
         * Kind of common with third parties, more so than us
         */
        if (!$optionsPayload) {
            return null;
        }

        /* Superimpose selected options over the default
         * Disabling fields prevents the keys from existing
         */
        return $this->mergeConfig($optionsPayload, $provider);
    }


    /**
     * Generate initial config structure for all modules based on provider
     *
     * @param string $provider
     * @return array
     */
    public static function generateInitConfig(string $provider): array
    {
        switch ($provider) {
            case self::PROVIDER_AZURE:
                $config = self::CONFIG_STRUCT_AZURE;
                break;
            case self::PROVIDER_OPENAI:
                $config = self::CONFIG_STRUCT_OPENAI;
                break;
            default:
                return [];
        }

        $result = [];
        foreach (self::ALL_MODULES as $module) {
            $result[$module] = $config;
        }
        return $result;
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
            case self::PROVIDER_AZURE:
                $expected = self::CONFIG_STRUCT_AZURE;
                break;
            case self::PROVIDER_OPENAI:
                $expected = self::CONFIG_STRUCT_OPENAI;
                break;
            default:
                return [];
        }

        $merged = [];
        foreach (self::ALL_MODULES as $module) {
            // Get existing config for this module if it exists
            $moduleConfig = isset($config[$module]) && is_array($config[$module])
                ? $config[$module]
                : [];

            // Only keep expected keys and overwrite defaults
            $merged[$module] = array_intersect_key($moduleConfig, $expected) + $expected;

            // Always set provider to avoid issues if switching providers
            $merged[$module]['provider'] = $provider;
        }

        return $merged;
    }
}
