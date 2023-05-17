<?php
/*
 * This script is a copy of updates/23.0.alpha1/calendarConnector/02-migrateData.php (the fixed version - fixed in SA-15683).
 * This script needs to be run again in 23.0.1 because some schools have already been upgraded beyond 23.0.alpha1 (and
 * so already ran the buggy version of this script).  The fixed script needs to be run on those schools.
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App
{
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP];
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Migrate serialized calendar_connector config data to JSON format

This script will read from schoolbox.calendar_connector._config and write to
schoolbox.calendar_connector.config.

Note that any existing contents of schoolbox.calendar_connector.config will be
overwritten by this script.

Note also that this script expects that the column named `_config` exists, and
that it contains the same data as was originally contained in the `config`
column.

For each row, the value written to the `config` column will be the data
from the corresponding `_config` column, transformed so that it is encoded in
JSON format instead of by the previous encoding method (which was to first
serialize and then base64encode a Calendar_Api_Config_Connector_Abstract
object).

Usage: php replace_serialized_calendar_config_with_json.php

HELP;
            exit;
        }

        $dbc = $this->getDatabaseInternal();

        echo 'Getting calendar_connector rows...';
        $rows = $dbc->executeQuery('SELECT id, _config FROM calendar_connector')->fetchAll();
        echo 'done.  Found ' . count($rows) . " rows.\n\n";

        echo "For each row in calendar_connector, updating `config` to the JSON equivalent of `_config`.\n";
        foreach ($rows as $row) {
            $configBase64Decoded = base64_decode($row['_config']);

            if ($configBase64Decoded === false) {
                echo " *  id {$row['id']} - Could not base64_decode() _config.  Skipping.\n";
                echo "   (_config: '{$row['_config']}')\n";
                continue;
            }

            $config = unserialize($configBase64Decoded, ['Calendar_Api_Config_Connector_Abstract']);

            if ($config === false) {
                echo " * id {$row['id']} - Could not unserialize() _configBase64Decoded.  Skipping.\n";
                echo "   (_config: '{$row['_config']}')\n";
                echo "   (_configBase64Decoded: '$configBase64Decoded')\n";
                continue;
            }

            $configJson = convertConfigObjectToJson($config);

            if ($configJson === false) {
                $configJson = ''; // If we could not convert to JSON, store as a blank string in the database.  Any connector that suffers this fate will have to be re-entered by a user.
            }

            printf(" * id: %3s, strlen(config): %4d, strlen(configJson): %4d\n", $row['id'], strlen($row['_config']), strlen($configJson));

            $query = (
                "UPDATE calendar_connector
                 SET config=:configJson
                 WHERE id=:id"
            );
            $statement = $dbc->prepareQuery($query, ['id' => $row['id'], 'configJson' => $configJson]);
            $statement->execute();
        }

        echo "\nDone.  " . count($rows) . " rows updated.\n";
    }

    /**
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();

// Functions. //////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * @param Calendar_Api_Config_Connector_Abstract $config
 * @return bool|string
 */
function convertConfigObjectToJson(Calendar_Api_Config_Connector_Abstract $config): bool|string
{
    $configArray = [
        'label'       => $config->getLabel(),
        'luEventType' => $config->getLuEventType(),
    ];

    switch (get_class($config)) {
        case Calendar_Api_Config_Connector_ExternalConstrained::class:
            $configArray['duration'] = $config->getDuration();
            break;

        case Calendar_Api_Config_Connector_Vcal::class:
            $configArray['url']           = $config->getUrl();
            $configArray['authorization'] = $config->getAuthorization();
            $configArray['campusIds']     = $config->getCampusIds();
            $configArray['groupIds']      = $config->getGroupIds();
            break;

        default:
            // Do nothing.
    }

    try {
        $configJson = json_encode($configArray, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return false;
    }

    return $configJson;
}
