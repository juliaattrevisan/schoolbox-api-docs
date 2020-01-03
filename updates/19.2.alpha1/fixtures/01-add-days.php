#!/usr/bin/php
<?php
/**
 * CLI Tool
 *
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP
Add the Days component config to Homepage Fixtures and Dashboard Fixtures components

Usage: 01-add-days.php

HELP;
            exit;
        }

        $this->addToHomepageComponents();
        $this->addToDashboardComponents();
    }


    /**
     * Assign Days config to every existence of a component using $componentDefinitionId
     *
     * @param int $componentDefinitionId
     */
    private function addToComponent(int $componentDefinitionId): void
    {
        /* Add a default config into the component config definition list
         */
        $query = "
            INSERT INTO component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
            VALUES (
                :componentDefinitionId,
                'days',
                0,
                'Days',
                null
            )
        ";

        $statement = $this->getDatabaseInternal()->prepareQuery($query);
        $statement->execute([
            'componentDefinitionId' => $componentDefinitionId
        ]);

        /* Find every Fixtures component instance
         */
        $query = '
            SELECT ci.id
            FROM component_instance ci
            INNER JOIN component_definition cd ON ci.component_definition_id = cd.id
            WHERE cd.id = :componentDefinitionId
        ';

        $statement = $this->getDatabaseInternal()->prepareQuery($query);
        $statement->execute([
            'componentDefinitionId' => $componentDefinitionId
        ]);

        /* Assign it to every component instance of Fixtures that exists in homepages
         */
        $insertStatement = $this->getDatabaseInternal()->prepareQuery('
            INSERT INTO component_config_instance (`component_config_definition_id`, `component_instance_id`, `value`)
            VALUES (
                :componentDefinitionId,
                :componentInstanceId,
                0
            )
        ');

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $componentInstanceId = $row['id'];

            $insertStatement->execute([
                'componentDefinitionId' => $componentDefinitionId,
                'componentInstanceId' => $componentInstanceId
            ]);
        }
    }


    /**
     * Add Days component config to all Homepage Fixtures instances
     */
    private function addToHomepageComponents(): void
    {
        /* Find the component definition id
         */
        $query = "
            SELECT id
            FROM component_definition
            WHERE `class` = 'Schoolbox\\\Fixtures\\\Component\\\Homepage\\\Controller'
        ";

        $statement = $this->getDatabaseInternal()->prepareQuery($query);
        $statement->execute();

        $componentDefinitionId = (int)$statement->fetchColumn();

        if ($componentDefinitionId) {
            $this->addToComponent($componentDefinitionId);
        }
    }


    /**
     * Add Days component config to all Dashboard Fixtures instances
     */
    private function addToDashboardComponents(): void
    {
        /* Find the component definition id
         */
        $query = "
            SELECT id
            FROM component_definition
            WHERE `class` = 'Schoolbox\\\Fixtures\\\Component\\\Dashboard\\\Controller'
        ";

        $statement = $this->getDatabaseInternal()->prepareQuery($query);
        $statement->execute();

        $componentDefinitionId = (int)$statement->fetchColumn();

        if ($componentDefinitionId) {
            $this->addToComponent($componentDefinitionId);
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
