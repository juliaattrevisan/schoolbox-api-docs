<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Migrates all old instances of Component_Dashboard_ImageSliderController to Component_Dashboard_ScrollerController
 */
$app = new class extends CliTool\App
{
    public function run(): void
    {
        $dbc = $this->getDatabaseInternal();

        $componentDefinitionIdOld = $dbc->executeQuery(
            'SELECT id FROM component_definition WHERE class = "Component_Dashboard_ImageSliderController"'
        )->fetchColumn();
        $componentDefinitionIdNew = $dbc->executeQuery(
            'SELECT id FROM component_definition WHERE class = "Component_Dashboard_ScrollerController"'
        )->fetchColumn();

        // All instances of the old image slider component
        // We only need to transfer components on the dashboard as the image slider was never used on homepages
        $this->getLogger()->info("Migrating image slider instances to image scroller (slideshow)...");
        $oldInstances = $dbc->executeQuery(
            'SELECT id FROM component_instance WHERE component_definition_id = :component_definition_id_old',
            ['component_definition_id_old' => $componentDefinitionIdOld]
        )->fetchAll();
        $this->getLogger()->info("  Found " . count($oldInstances) . " instances to migrate");
        $this->getLogger()->info("    - Old component ID: " . $componentDefinitionIdOld);
        $this->getLogger()->info("    - New component ID: " . $componentDefinitionIdNew);

        foreach ($oldInstances as $oldInstance) {
            $this->getLogger()->info("  Migrating slider " . $oldInstance['id'] . "...");

            // Update each instance to use the ID for the image scroller (slideshow) component
            $this->getLogger()->info("    - Updating component_definition_id to " . $componentDefinitionIdNew . "...");
            $dbc->executeQuery(
                'UPDATE component_instance SET component_definition_id = :component_definition_id_new WHERE id = :id',
                ['id' => $oldInstance['id'], 'component_definition_id_new' => $componentDefinitionIdNew]
            );
            $this->getLogger()->info(" done.");

            // Update old configs to reference the component_config_definition_id for image scroller not image slider
            $this->getLogger()->info("    - Updating component_config_definition_id for old configs...");
            $oldKeysToTransfer = ["componentname", "pixel", "src", "timing", "compactMode", "sort", "sortdir"];
            foreach ($oldKeysToTransfer as $oldKey) {
                $dbc->executeQuery(
                    'UPDATE component_config_instance
                        SET component_config_definition_id = (
                            SELECT id FROM component_config_definition
                            WHERE `key` = :old_key AND component_definition_id = :component_definition_id_new
                        )
                        WHERE
                            component_config_definition_id = (
                                SELECT id FROM component_config_definition
                                WHERE `key` = :old_key AND component_definition_id = :component_definition_id_old
                            )
                            AND component_instance_id = :old_instance_id',
                    [
                        'component_definition_id_new' => $componentDefinitionIdNew,
                        'component_definition_id_old' => $componentDefinitionIdOld,
                        'old_key' => $oldKey,
                        'old_instance_id' => $oldInstance['id']
                    ]
                );
            }
            $this->getLogger()->info(" done.");

            // Create new configs - these were not present in the old image slider
            $this->getLogger()->info("    - Creating new configs to sync with image slideshow...");
            $newKeysToCreate = [
                ["captionState", "Inactive"],
                ["imageRatio", "auto"],
                ["randomStartingSlide", 0],
                ["transition", "swipe"]
            ];
            foreach ($newKeysToCreate as $newKey) {
                $dbc->executeQuery(
                    'INSERT INTO component_config_instance (component_config_definition_id, component_instance_id, value)
                    VALUES (
                        (
                            SELECT id FROM component_config_definition
                            WHERE `key` = :new_key AND component_definition_id = :component_definition_id_new
                        ),
                        :old_instance_id,
                        :new_value
                    )',
                    [
                        'old_instance_id' => $oldInstance['id'],
                        'new_key' => $newKey[0],
                        'new_value' => $newKey[1],
                        'component_definition_id_new' => $componentDefinitionIdNew
                    ]
                );
            }
            $this->getLogger()->info(". done.");
        }

        $this->getLogger()->info("  Deleting old image slider configs...");
        // Delete old config definitions for image slider, as these are no longer used
        $this->getLogger()->info("    - Old config definitions...");
        $dbc->executeQuery(
            'DELETE FROM component_config_definition WHERE component_definition_id = :component_definition_id_old',
            ['component_definition_id_old' => $componentDefinitionIdOld]
        );
        $this->getLogger()->info(" removed.");

        // Delete old component definition for image slider, as this is no longer used
        $this->getLogger()->info("    - Old component definition...");
        $dbc->executeQuery(
            'DELETE FROM component_definition WHERE id = :component_definition_id_old',
            ['component_definition_id_old' => $componentDefinitionIdOld]
        );
        $this->getLogger()->info(" removed.");
        $this->getLogger()->info("Done. Finished migrating " . count($oldInstances) . " image slider instances");
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel(
    $app,
    [Schoolbox\Application\CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]
))->runApp();
