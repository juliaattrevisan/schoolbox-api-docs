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

/**
 * Add the Days component config to Homepage and Dashboard Fixtures components
 */
$app = new class extends CliTool\App {
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /* Component Config specification
     */
    private const KEY = 'days';
    private const DEFAULT_VALUE = '0';
    private const LABEL = 'Days';
    private const DESCRIPTION = null;

    /* Component Controllers
     */
    private const HOMEPAGE_CLASS = 'Schoolbox\\Fixtures\\Component\\Homepage\\Controller';
    private const DASHBOARD_CLASS = 'Schoolbox\\Fixtures\\Component\\Dashboard\\Controller';


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

        $this->addToHomepageComponents(self::HOMEPAGE_CLASS);
        $this->addToDashboardComponents(self::DASHBOARD_CLASS);
    }


    /**
     * Add Days component config to all Homepage Fixtures instances
     *
     * @param string $class
     */
    private function addToHomepageComponents(string $class): void
    {
        $componentDefinitionId = $this->findComponentDefinitionId($class);

        if ($componentDefinitionId) {
            $this->addToComponent($componentDefinitionId, self::KEY, self::DEFAULT_VALUE, self::LABEL, self::DESCRIPTION);
        } else {
            $this->getLogger()->warning("Component definition id didn't exist for homepage component; skipping");
        }
    }


    /**
     * Add Days component config to all Dashboard Fixtures instances
     *
     * @param string $class
     */
    private function addToDashboardComponents(string $class): void
    {
        $componentDefinitionId = $this->findComponentDefinitionId($class);

        if ($componentDefinitionId) {
            $this->addToComponent($componentDefinitionId, self::KEY, self::DEFAULT_VALUE, self::LABEL, self::DESCRIPTION);
        } else {
            $this->getLogger()->warning("Component definition id didn't exist for dashboard component; skipping");
        }
    }


    /**
     * Find the component definition id belonging to $class
     *
     * @param string $class
     * @return int
     */
    private function findComponentDefinitionId(string $class): int
    {
        $query = '
            SELECT id
            FROM component_definition
            WHERE `class` = :class
        ';

        $statement = $this->getDatabaseInternal()->prepareQuery($query);
        $statement->execute([
            'class' => $class
        ]);

        return (int)$statement->fetchColumn();
    }


    /**
     * Assign Days config to every existence of a component using $componentDefinitionId
     *
     * @param int         $componentDefinitionId
     * @param string      $key
     * @param string      $defaultValue
     * @param string      $label
     * @param string|null $description
     */
    private function addToComponent(int $componentDefinitionId, string $key, string $defaultValue, string $label, string $description = null): void
    {
        /* Add a default config into the component config definition list
         */
        $query = '
            INSERT INTO component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
            VALUES (
                :componentDefinitionId,
                :key,
                :defaultValue,
                :label,
                :description
            )
        ';

        $statement = $this->getDatabaseInternal()->prepareQuery($query);
        $statement->execute([
            'componentDefinitionId' => $componentDefinitionId,
            'key' => $key,
            'defaultValue' => $defaultValue,
            'label' => $label,
            'description' => $description
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

            $this->getLogger()->info(sprintf(
                'Assigning component definition id %s to component instance %s',
                $componentDefinitionId,
                $componentInstanceId
            ));

            $insertStatement->execute([
                'componentDefinitionId' => $componentDefinitionId,
                'componentInstanceId' => $componentInstanceId
            ]);
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
