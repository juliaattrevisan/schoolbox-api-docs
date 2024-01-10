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
            fo.id AS folderId,
            ci.id AS componentInstanceId,
            cci_textbox.textBoxID AS textboxId,
            cci_textbox.heading AS textboxHeading,
            cci_componentname.value AS componentName,
            cci_componentname.component_instance_id AS componentNameId
            FROM component_instance ci
            JOIN folder fo ON fo.id=ci.identifier
            JOIN (
                SELECT cci1.component_instance_id, tb.textBoxID, tb.heading, tb.updated_at, tb.created_by, tb.deleted_at, tb.body
                FROM component_config_instance cci1
                JOIN component_config_definition ccd1 ON (
                    ccd1.key="' . TextboxMapper::HOMEPAGE_COMPONENT_KEY . '" AND
                    ccd1.id=cci1.component_config_definition_id
                )
                JOIN textBox tb ON CAST(tb.textBoxID AS NCHAR)=cci1.value
            ) AS cci_textbox ON (cci_textbox.component_instance_id=ci.id)
            LEFT JOIN (
                SELECT cci2.component_instance_id, cci2.value
                FROM component_config_instance cci2
                JOIN component_config_definition ccd2 ON (
                    ccd2.key = "' . Component_Config_Componentname::KEY . '" AND
                    ccd2.id=cci2.component_config_definition_id
                )
            ) AS cci_componentname ON (cci_componentname.component_instance_id=ci.id)
            WHERE ci.module="' . Component_Definition::MODULE_HOMEPAGE . '"
            AND (cci_componentname.value IS NULL OR TRIM(cci_componentname.value) = "")'
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
