#!/usr/bin/env php
<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\OpenAi\Feature;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {
    public function run(): void
    {
        /** using \Schoolbox\Admin\OpenAI\Config::migrate
         * we should migrate all schools from the old config type to the new system (leaving the old creds alone)
         * this should use direct DB NOT mappers, as we don't use mappers in upgrade scripts
         */

        // grab the creds from the old config
        $oldConfig = $this->getDatabaseInternal()->executeQuery("
            SELECT name,value FROM config WHERE name in (
                'openai_image_endpoint',
                'openai_image_key',
                'openai_image_azure_resource_name',
                'openai_image_azure_deployment_id',
                'openai_image_model',
                'openai_image_system_prompt',
                'openai_image_output_quality'
            )
        ");

        //clean config into array
        $conf = [];
        while ($row = $oldConfig->fetch(\PDO::FETCH_ASSOC)) {
            $conf[$row['name']] = $row['value'];
        }

        // migrate to new config format
        $newConfig = $this->migrate(
            $conf['openai_image_endpoint'] ?? null,
            $conf['openai_image_key'] ?? null,
            $conf['openai_image_azure_resource_name'] ?? null,
            $conf['openai_image_azure_deployment_id'] ?? null,
            $conf['openai_image_model'] ?? null,
            $conf['openai_image_system_prompt'] ?? null,
            $conf['openai_image_output_quality'] ?? null,
        );

        // log provider
        $this->getLogger()->notice("Migrated AI image generation config, provider: " . ($newConfig['provider']['value'] ?? 'unknown'));

        $query = $this->getDatabaseInternal()->prepareQuery("UPDATE config SET value = :value WHERE name = :name;");
        $config = new Config();

        // insert new config into DB
        $this->getLogger()->notice(
            sprintf(
                "Migrating AI config for module '%s' into '%s', provider: %s",
                Feature::IMAGE->value,
                ConfigMapper::NAME_AI_IMAGE_GENERATION,
                $newConfig['provider']['value'] ?? 'unknown',
            )
        );
        $this->getLogger()->debug("New config: " . $config->encode($newConfig, Feature::IMAGE->value));
        $query->execute(
            [
                'name'  => ConfigMapper::NAME_AI_IMAGE_GENERATION,
                'value' => $config->encode($newConfig, Feature::IMAGE->value),
            ]
        );
    }
    public function runAsRoot(): ?bool
    {
        return null;
    }

    /**
     * migrate from the old config to the new config structure (we won't need to reuse this, so its now in the upgrade script)
     *
     * @param string|null $provider
     * @param string|null $key
     * @param string|null $resourceName
     * @param string|null $deploymentId
     * @param string|null $model
     * @param string|null $systemPrompt
     * @param string|null $outputQuality
     * @return array
     */
    private function migrate(
        ?string $provider,
        ?string $key,
        ?string $resourceName,
        ?string $deploymentId,
        ?string $model,
        ?string $systemPrompt,
        ?string $outputQuality
    ): array {
        $configHelper = new Config();
        // start with a blank config
        $config = [Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OFF]];
        $config[Helper::ENDPOINT_TYPE_GENERAL] = $configHelper->initGeneral(Feature::IMAGE->value);
        $config[Helper::ENDPOINT_TYPE_OPENAI] = $configHelper->initOpenAi(Feature::IMAGE->value);
        $config[Helper::ENDPOINT_TYPE_AZURE] = $configHelper->initAzure(Feature::IMAGE->value);

        $config[Helper::ENDPOINT_TYPE_GENERAL][Config::FIELD_AIKEY]['value'] = $key ?? '';
        $config[Helper::ENDPOINT_TYPE_GENERAL][Config::FIELD_SYSTEM_PROMPT]['value']  = $systemPrompt ?? '';
        // set the fields we know about
        if ($provider === Helper::ENDPOINT_TYPE_AZURE) {
            // Should never be Azure for image generation, as that's not enabled:
            // nonetheless, support it?
            $config[Config::FIELD_PROVIDER]['value'] = Helper::ENDPOINT_TYPE_AZURE;
            $config[Helper::ENDPOINT_TYPE_AZURE][Config::FIELD_RESOURCE_NAME]['value'] = $resourceName ?? '';
            $config[Helper::ENDPOINT_TYPE_AZURE][Config::FIELD_DEPLOYMENT_ID]['value'] = $deploymentId ?? '';
        } elseif ($provider === Helper::ENDPOINT_TYPE_OPENAI) {
            $config[Config::FIELD_PROVIDER]['value'] = Helper::ENDPOINT_TYPE_OPENAI;
            // var the model, so we can set a default if missing (will grab the end of the array (newest model))
            $systemModels = Config::AI_MODELS;
            $config[Helper::ENDPOINT_TYPE_OPENAI][Config::FIELD_MODEL]['value'] = $model ?? end($systemModels);
            $config[Helper::ENDPOINT_TYPE_OPENAI][Config::FIELD_OUTPUT_QUALITY]['value'] = $outputQuality ?? '';
        }
        return $config;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
