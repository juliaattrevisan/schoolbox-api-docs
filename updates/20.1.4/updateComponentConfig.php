#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';


$app = new class extends CliTool\App {
    /* @var array */

    /** Hands off execution over to the App */
    public function run(): void
    {
        // get bread crumb and tile component definition for homepage
        $tileComponentDefinition = $this->getDatabaseInternal()->executeQuery(
            'SELECT `id` FROM component_definition WHERE `class` = :class',
            ['class' => \Schoolbox\Tile\Component\HomepageTileController::class]
        )->fetchColumn();

        $breadcrumbComponentDefinition = $this->getDatabaseInternal()->executeQuery(
            'SELECT `id` FROM component_definition WHERE `class` = :class',
            ['class' => 'Component_Homepage_BreadcrumbController']
        )->fetchColumn();

        // find component config definition of "collapse" for tile and homepage
        $collapseConfigDefinitions = $this->getDatabaseInternal()->executeQuery(
            'SELECT `id` FROM component_config_definition 
             WHERE component_definition_id in (:id1, :id2)
               AND `key` = "collapse"',
            [
                'id1' => $tileComponentDefinition,
                'id2' => $breadcrumbComponentDefinition,
            ]
        )->fetchColumnArray('id');

        $collapseConfigPredicate = PredicateBuilder::getArrayBindings($collapseConfigDefinitions, 'ccd');

        // find component instance with NULL for component config instance = collapse
        $collapseNullComponents = $this->getDatabaseInternal()->executeQuery(
            "SELECT component_instance_id FROM component_config_instance 
             WHERE component_config_definition_id IN ({$collapseConfigPredicate['placeholders']})
             AND `value` IS NULL",
            $collapseConfigPredicate['bindings']
        )->fetchColumnArray('component_instance_id');

        // find component config definition of "showComponentTitle" for tile and homepage
        $showComponentTitleConfigDefinitions = $this->getDatabaseInternal()->executeQuery(
            'SELECT `id` FROM component_config_definition 
             WHERE component_definition_id in (:id1, :id2)
             AND `key` = "showComponentTitle"',
            [
                'id1' => $tileComponentDefinition,
                'id2' => $breadcrumbComponentDefinition,
            ]
        )->fetchColumnArray('id');

        $showTitleConfigPredicate = PredicateBuilder::getArrayBindings($showComponentTitleConfigDefinitions, 'ccd');

        $this->getLogger()->debug('Update component_config_instance of component_config_definition_id:' . implode(',', $showComponentTitleConfigDefinitions));
        // find component config instance compact mode of the config instance
        foreach ($collapseNullComponents as $collapseNullComponent) {
            $originalValue = $this->getDatabaseInternal()->executeQuery(
                "SELECT `value` FROM component_config_instance
                 WHERE component_config_definition_id in ({$showTitleConfigPredicate['placeholders']})
                 AND component_instance_id = :componentInstanceId",
                array_merge($showTitleConfigPredicate['bindings'], ['componentInstanceId' => $collapseNullComponent])
            )->fetchColumn();
            $this->getLogger()->debug("Updating component_instance: {$collapseNullComponent}, original value: " . ($originalValue ?? 'NULL'));
            $this->getDatabaseInternal()->executeQuery(
                "UPDATE component_config_instance SET value = 0 
                 WHERE component_config_definition_id in ({$showTitleConfigPredicate['placeholders']})
                 AND component_instance_id = :componentInstanceId",
                array_merge($showTitleConfigPredicate['bindings'], ['componentInstanceId' => $collapseNullComponent])
            );
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
