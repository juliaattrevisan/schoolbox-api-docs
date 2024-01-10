<?php

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Component_Config_Componentname;
use Component_Definition;
use Monolog;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Textbox\Component\Homepage\Controller as TextboxComponentController;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Soft deletes old release tours
 */
$app = new class extends CliTool\App {
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';

    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo "This script adds component names to each homepage textbox component that either:\n";
            echo " * does not have a name, OR\n";
            echo " * has a blank name\n\n";
            echo "The added component name will be:\n";
            echo " * the textBox.heading if that is non-null,\n";
            echo " * otherwise the default heading for a textbox component (" . TextboxComponentController::DEFAULT_COMPONENT_HEADING . ")\n\n";
            echo "Usage: php " . __FILE__ . "\n\n";
            exit;
        }

        $dbc = $this->getDatabaseInternal(); // Database connector.
        $logger = $this->getLogger();

        $rows = $dbc->executeQuery(
            'SELECT
            f.id AS folderId,
            ci.id AS componentInstanceId,
            tb.textBoxID AS textboxId,
            tb.heading AS textboxHeading,
            cci_componentname.value AS componentName,
            cci_componentname.component_instance_id AS componentNameId
            FROM component_definition cd
            JOIN component_instance ci ON ci.component_definition_id = cd.id
            JOIN folder f ON f.id=ci.identifier
            JOIN component_config_definition ccd_textbox ON (
                ccd_textbox.component_definition_id = cd.id AND
                ccd_textbox.key = "' . TextboxMapper::HOMEPAGE_COMPONENT_KEY . '"
            )
            LEFT JOIN component_config_instance cci_textbox ON (
                cci_textbox.component_config_definition_id = ccd_textbox.id AND
                cci_textbox.component_instance_id = ci.id
            )
            JOIN textBox tb ON CAST(tb.textBoxID AS NCHAR) = cci_textbox.value
            JOIN component_config_definition ccd_componentname ON (
                ccd_componentname.component_definition_id = cd.id AND
                ccd_componentname.key = "' . Component_Config_Componentname::KEY . '"
            )
            LEFT JOIN component_config_instance cci_componentname ON (
                cci_componentname.component_config_definition_id = ccd_componentname.id AND
                cci_componentname.component_instance_id=ci.id
            )
            WHERE cd.`class` = "Schoolbox\\\Resource\\\Textbox\\\Component\\\Homepage\\\Controller"
            AND (cci_componentname.value IS NULL OR TRIM(cci_componentname.value) = "")'
        )->fetchAll();

        if (count($rows) === 0) {
            $logger->info("No unnamed homepage textbox components found, so nothing to do.\n");
            die;
        }

        $componentNameCcdId = $dbc->executeQuery(
            'SELECT id
            FROM component_config_definition
            WHERE component_definition_id = (
                SELECT id
                FROM component_definition
                WHERE class = "Schoolbox\\\Resource\\\Textbox\\\Component\\\Homepage\\\Controller"
            )
            AND `key` = "' . Component_Config_Componentname::KEY . '"'
        )->fetchColumn();

        $insertCommandParamsGroups = [];
        $updateCommandParamsGroups = [];
        foreach ($rows as $row) {
            $paramGroup = [
                ':componentConfigDefinitionId' => $componentNameCcdId,
                ':componentInstanceId' => $row['componentInstanceId'],
                ':value' => (
                    ($row['textboxHeading'] === null || trim($row['textboxHeading']) === '')
                    ? TextboxComponentController::DEFAULT_COMPONENT_HEADING
                    : trim($row['textboxHeading'])
                )
            ];

            if ($row['componentNameId'] === null) {
                $insertCommandParamsGroups[] = $paramGroup;
            } else {
                $updateCommandParamsGroups[] = $paramGroup;
            }
        }

        $dbc->beginTransaction();
        // Prepare the query outside the loop.
        $stmt = $dbc->prepareQuery(
            'INSERT INTO component_config_instance SET
            component_config_definition_id = :componentConfigDefinitionId,
            component_instance_id = :componentInstanceId,
            value = :value'
        );
        foreach ($insertCommandParamsGroups as $paramsGroup) {
            // Bind the parameters and execute the query inside the loop.
            // Since we have prepared the query outside the loop, this should be fast enough.
            $stmt->execute($paramsGroup);
        }
        $dbc->commit();
        $logger->info("Inserted " . count($insertCommandParamsGroups) . " rows for homepage textbox component names.\n");

        $dbc->beginTransaction();
        // Prepare the query outside the loop.
        $stmt = $dbc->prepareQuery(
            'UPDATE component_config_instance SET
            value = :value
            WHERE component_config_definition_id = :componentConfigDefinitionId
            AND component_instance_id = :componentInstanceId'
        );
        foreach ($updateCommandParamsGroups as $paramsGroup) {
            // Bind the parameters and execute the query inside the loop.
            // Since we have prepared the query outside the loop, this should be fast enough.
            $stmt->execute($paramsGroup);
        }
        $dbc->commit();
        $logger->info("Updated " . count($updateCommandParamsGroups) . " rows for homepage textbox component names.\n");
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

$options = [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::DEBUG, CliKernelAbstract::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new \Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
