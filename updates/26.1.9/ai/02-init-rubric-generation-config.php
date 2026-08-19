#!/usr/bin/env php
<?php

/*
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Editor\OpenAI\Helper;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {
    public function run(): void
    {
        $config  = new Config();
        $feature = \Schoolbox\OpenAi\Feature::RUBRIC_GENERATION->value;
        $value   = [Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OFF]];

        $value[Helper::ENDPOINT_TYPE_GENERAL] = $config->initGeneral($feature);
        $value[Helper::ENDPOINT_TYPE_OPENAI]  = $config->initOpenAi($feature);
        $value[Helper::ENDPOINT_TYPE_AZURE]   = $config->initAzure($feature);

        $this->getDatabaseInternal()
            ->prepareQuery('UPDATE config SET value = :value WHERE name = :name')
            ->execute([
                'name' => ConfigMapper::NAME_AI_RUBRIC_GENERATION,
                'value' => $config->encode($value, $feature),
            ]);
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
