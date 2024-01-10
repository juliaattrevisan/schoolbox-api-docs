<?php

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Component_Config_Componentname;
use Component_Definition;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Textbox\Component\Homepage\Controller as TextboxComponentController;
use Monolog;

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
            // The point of the IFNULL(cci2.value, '') in the query below is to differentiate between
            // the cases where 1) the row does not exist, and 2) where the row exists but the value is null.
            // Differentiation is necessary because case 1 requires an INSERT, while case 2 requires an UPDATE.
            'SELECT
            fo.id as folderId,
            cci.component_instance_id AS componentInstanceId,
            tb.textBoxID AS textboxId,
            tb.heading AS textboxHeading,
            (
                SELECT IFNULL(cci2.value, "") AS value
                FROM component_config_instance cci2
                JOIN component_config_definition ccd2 ON (
                    ccd2.key = "' . Component_Config_Componentname::KEY . '" AND
                    ccd2.id=cci2.component_config_definition_id
                )
                WHERE cci2.component_instance_id=ci.id
            ) AS componentName
            FROM component_instance ci
            JOIN component_config_instance cci ON cci.component_instance_id=ci.id
            JOIN component_config_definition ccd ON (
                ccd.key="textbox" AND
                ccd.id=cci.component_config_definition_id
            )
            JOIN textBox tb ON CAST(tb.textBoxID AS NCHAR)=cci.value
            JOIN folder fo ON fo.id=ci.identifier
            WHERE ci.module="' . Component_Definition::MODULE_HOMEPAGE . '"
            HAVING componentName IS NULL OR (TRIM(componentName) = "" AND TRIM(textboxHeading) <> "")
            ORDER BY fo.id ASC, cci.component_instance_id ASC, tb.textBoxID ASC'
        )->fetchAll();

        if (count($rows) === 0) {
            $logger->info("No unnamed homepage textbox components found, so nothing to do.\n");
            die;
        }

        $componentNameCcdId = $dbc->executeQuery(
            'SELECT ccd.id
            FROM component_config_definition AS ccd
            JOIN component_definition AS cd ON cd.id = ccd.component_definition_id
            WHERE cd.module = "' . Component_Definition::MODULE_HOMEPAGE . '"
            AND cd.name = "Text Box"
            AND ccd.`key` = "' . Component_Config_Componentname::KEY . '"'
        )->fetchColumn();

        $insertCommandParamsGroups = [];
        $updateCommandParamsGroups = [];
        foreach ($rows as $row) {
            $paramGroup = [
                ':componentConfigDefinitionId' => $componentNameCcdId,
                ':componentInstanceId' => $row['componentInstanceId'],
                ':value' => $row['textboxHeading'] ?? TextboxComponentController::DEFAULT_COMPONENT_HEADING
            ];

            if ($row['componentName'] === null) {
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
