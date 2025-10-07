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
                    )");

        //clean config into array
        $conf = [];
        while ($row = $oldConfig->fetch(\PDO::FETCH_ASSOC)) {
            $conf[$row['name']] = $row['value'];
        }

        // migrate to new config format
        $newConfig = Schoolbox\Admin\OpenAI\Config::migrate($conf['openai_text_endpoint'] ?? null,
            $conf['openai_text_key'] ?? null,
            $conf['openai_text_azure_resource_name'] ?? null,
            $conf['openai_text_azure_deployment_id'] ?? null,
            $conf['openai_text_model'] ?? null);

        // insert new config into DB
        foreach (Schoolbox\Admin\OpenAI\Config::MODULE_CONFIG as $module => $configName) {
            if (!isset($newConfig[$module])) {
                $this->getLogger()->warning("No new config for module $module, skipping");
                // nothing to do
                continue;
            }

            $this->getLogger()->notice("Migrating OpenAI config for module $module");
            $this->getDatabaseInternal()->executeQuery(
                "UPDATE config SET value = :value WHERE name = :name;",
                [
                    'name' => $configName,
                    'value' => json_encode($newConfig[$module], JSON_THROW_ON_ERROR),
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
