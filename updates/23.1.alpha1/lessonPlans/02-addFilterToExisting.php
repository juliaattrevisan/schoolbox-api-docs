<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Updates the Course Outline component to include the new filter field
 */
$app = new class extends CliTool\App
{
    public function run(): void
    {
        $dbc = $this->getDatabaseInternal();

        $componentDefinitionId = $dbc->executeQuery(
            "SELECT id FROM component_definition WHERE class = 'Schoolbox\\\Learning\\\Assessment\\\Component\\\Homepage\\\Outline\\\Controller'"
        )->fetchColumn();

        $oldInstances = $dbc->executeQuery(
            'SELECT id FROM component_instance WHERE component_definition_id = :component_definition_id',
            ['component_definition_id' => $componentDefinitionId]
        )->fetchAll();

        // All instances of the old image slider component
        // We only need to transfer components on the dashboard as the image slider was never used on homepages
        $this->getLogger()->info("Updating existing Course outline components to contain the new filter config option");

        foreach ($oldInstances as $oldInstance) {
            $this->getLogger()->info("  Updating instance: " . $oldInstance['id'] . "...");
            // Create new configs - these were not present in the old image slider
            $this->getLogger()->info("    - Creating new filter config...");

            $dbc->executeQuery(
                'INSERT INTO component_config_instance (component_config_definition_id, component_instance_id, value)
                VALUES (
                    (
                        SELECT id FROM component_config_definition
                        WHERE `key` = "assessmentTypes" AND component_definition_id = :component_definition_id_new
                    ),
                    :old_instance_id,
                    (
                        SELECT default_value FROM component_config_definition
                        WHERE `key` = "assessmentTypes" AND component_definition_id = :component_definition_id_new
                    )
                )',
                [
                    'old_instance_id' => $oldInstance['id'],
                    'component_definition_id_new' => $componentDefinitionId
                ]
            );
            $this->getLogger()->info(". config added.");
        }
        $this->getLogger()->info("Done. Finished migrating " . count($oldInstances) . " Course Outline components");
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }

};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel(
    $app,
    [Schoolbox\Application\CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]
))->runApp();
