#!/usr/bin/php
<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {
    public function run(): void
    {
        /** using \Schoolbox\Admin\OpenAI\Config::migrate
         * we should migrate all schools from the old config type to the new system (leaving the old creds alone)
         * this should use direct DB NOT mappers, as we don't use mappers in upgrade scripts
         */

        // grab the creds from the old config
        $oldConfig = $this->getDatabaseInternal()->executeQuery(
            "SELECT name,value FROM config WHERE name in (
                    'openai_text_endpoint',
                    'openai_text_key',
                    'openai_text_azure_resource_name',
                    'openai_text_azure_deployment_id',
                    'openai_text_model'
                    )"
        );

        //clean config into array
        $conf = [];
        while ($row = $oldConfig->fetch(\PDO::FETCH_ASSOC)) {
            $conf[$row['name']] = $row['value'];
        }

        // migrate to new config format
        $newConfig = Schoolbox\Admin\OpenAI\Config::migrate(
            $conf['openai_text_endpoint'] ?? null,
            $conf['openai_text_key'] ?? null,
            $conf['openai_text_azure_resource_name'] ?? null,
            $conf['openai_text_azure_deployment_id'] ?? null,
            $conf['openai_text_model'] ?? null
        );

        // log provider
        $this->getLogger()->notice("Migrated AI text config, provider: " . ($newConfig['provider'] ?? 'unknown'));

        // insert new config into DB
        foreach (['openai_text_chat', 'openai_lessonplan', 'openai_assessment', 'openai_differentiation'] as $moduleKey) {
            $this->getLogger()->notice("Migrating AI config for module $moduleKey");
            $this->getDatabaseInternal()->executeQuery(
                "UPDATE config SET value = :value WHERE name = :name;",
                [
                    'name' => $moduleKey,
                    'value' => json_encode($newConfig, JSON_THROW_ON_ERROR),
                ]
            );
        }
    }
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
