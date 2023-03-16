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

        $component_definition_id_old = $dbc->executeQuery('SELECT id FROM component_definition WHERE class = "Component_Dashboard_ImageSliderController"')->fetchColumn();
        $component_definition_id_new = $dbc->executeQuery('SELECT id FROM component_definition WHERE class = "Component_Dashboard_ScrollerController"')->fetchColumn();

        // All instances of the old image slider component
        // We only need to transfer components on the dashboard as the image slider was never used on homepages
        echo "Migrating image slider instances to image scroller (slideshow)...";
        $oldInstances = $dbc->executeQuery('SELECT id FROM component_instance WHERE component_definition_id = :component_definition_id_old', ['component_definition_id_old' => $component_definition_id_old])->fetchAll();
        echo "  Found " . count($oldInstances) . " instances to migrate\n";
        echo "    - Old component ID: " . $component_definition_id_old . "\n";
        echo "    - New component ID: " . $component_definition_id_new . "\n\n";

        foreach ($oldInstances as $oldInstance) {
            echo "  Migrating slider " . $oldInstance['id'] . "...\n";

            // Update each instance to use the ID for the image scroller (slideshow) component
            echo "    - Updating component_definition_id to " . $component_definition_id_new . "...";
            $dbc->executeQuery(
                'UPDATE component_instance SET component_definition_id = :component_definition_id_new WHERE id = :id',
                ['id' => $oldInstance['id'], 'component_definition_id_new' => $component_definition_id_new]
            );
            echo " done.\n";

            // Update old configs to reference the component_config_definition_id for image scroller not image slider
            echo "    - Updating component_config_definition_id for old configs...";
            $old_keys_to_transfer = ["componentname", "pixel", "src", "timing", "compactMode", "sort", "sortdir"];
            foreach ($old_keys_to_transfer as $old_key) {
                $dbc->executeQuery(
                    'UPDATE component_config_instance
                        SET component_config_definition_id = (SELECT id FROM component_config_definition WHERE `key` = :old_key AND component_definition_id = :component_definition_id_new)
                        WHERE
                            component_config_definition_id = (SELECT id FROM component_config_definition WHERE `key` = :old_key AND component_definition_id = :component_definition_id_old)
                            AND component_instance_id = :old_instance_id',
                    ['component_definition_id_new' => $component_definition_id_new, 'component_definition_id_old' => $component_definition_id_old, 'old_key' => $old_key, 'old_instance_id' => $oldInstance['id']]
                );
            }
            echo " done.\n";

            // Create new configs - these were not present in the old image slider
            echo "    - Creating new configs to sync with image slideshow...";
            $new_keys_to_create = [
                ["captionState", "Inactive"],
                ["imageRatio", "auto"],
                ["randomStartingSlide", 0],
                ["transition", "swipe"]
            ];
            foreach ($new_keys_to_create as $new_key) {
                $dbc->executeQuery(
                    'INSERT INTO component_config_instance (component_config_definition_id, component_instance_id, value) VALUES ((SELECT id FROM component_config_definition WHERE `key` = :new_key AND component_definition_id = :component_definition_id_new), :old_instance_id, :new_value)',
                    ['old_instance_id' => $oldInstance['id'], 'new_key' => $new_key[0], 'new_value' => $new_key[1], 'component_definition_id_new' => $component_definition_id_new]
                );
            }
            echo ". done.\n";
        }

        foreach ($oldInstances as $oldInstance) {
            echo "  Deleting old image slider configs for " . $oldInstance['id'] . "...\n";
            // Delete old config definitions for image slider, as these are no longer used
            echo "    - Old config definitions...";
            $dbc->executeQuery(
                'DELETE FROM component_config_definition WHERE component_definition_id = :component_definition_id_old',
                ['component_definition_id_old' => $component_definition_id_old]
            );
            echo " removed.\n";

            // Delete old component definition for image slider, as this is no longer used
            echo "    - Old component definition...";
            $dbc->executeQuery(
                'DELETE FROM component_definition WHERE id = :component_definition_id_old',
                ['component_definition_id_old' => $component_definition_id_old]
            );
            echo " removed.\n";
        }
        echo "\nDone. Finished migrating " . count($oldInstances) . " image slider instances\n\n";
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
