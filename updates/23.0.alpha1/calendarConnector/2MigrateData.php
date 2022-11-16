<?php

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

Migrate serialized calendar_connector config data to JSON format

This script will read from schoolbox.calendar_connector.config and write to
schoolbox.calendar_connector.config_json.

For each row, the value written to the `config_json` column will be the data
from the corresponding `config` column, transformed so that it is encoded in
JSON format instead

Usage: php replace_serialized_calendar_config_with_json.php

HELP;
            exit;
        }

        $dbc = $this->getDatabaseInternal();

        echo 'Getting calendar_connector rows...';
        $rows = $dbc->executeQuery('SELECT id, config FROM calendar_connector')->fetchAll();
        echo 'done.  Found ' . count($rows) . " rows.\n\n";

        echo "For each row in calendar_connector, updating `config_json` to the JSON equivalent of `config`.\n";
        foreach ($rows as $row) {
            $config = unserialize(base64_decode($row['config']), ['Calendar_Api_Config_Connector_Abstract']);

            $configJson = convertConfigObjectToJsonNotUsingEval($config);

            if ($configJson === false) {
                $configJson = null; // If we could not convert to JSON, store as NULL in the database.
            }

            printf(" * id: %3s, strlen(config): %4d, strlen(config_json): %4d\n", $row['id'], strlen($row['config']), strlen($configJson));

            $query = (
                "UPDATE calendar_connector
                 SET config_json=:configJson
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
function convertConfigObjectToJsonNotUsingEval(Calendar_Api_Config_Connector_Abstract $config): bool|string
{
    try {
        $configJson = json_encode(
            [
                'connectorClass' => $config->getConnectorClass(),
                'id'             => $config->getId(),
                'label'          => $config->getLabel(),
                'eventType'      => $config->getEventType(),
                'luEventType'    => $config->getLuEventType(),
                'hasPermissions' => false, // The serialized value will always be zero, because at the time this script was written, no class that extends Calendar_Api_Config_Connector_Abstract ever changes the private $hasPermissions variable from its default value of false.
                'failedCount'    => $config->getFailedCount(),
            ],
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException) {
        return false;
    }

    return $configJson;
}

/**
 * @param Calendar_Api_Config_Connector_Abstract $config
 * @return bool|string
 *
 * This is an alternative method to convertConfigObjectToJsonNotUsingEval(), for use in cases where the serialized
 * object no longer matches the current version of the Calendar_Api_Config_Connector_Abstract class.  This function
 * will probably not be needed.
 *
 * The non-eval version of this function should be preferred over this one because of the dangers of using eval().
 */
function convertConfigObjectToJsonUsingEval(Calendar_Api_Config_Connector_Abstract $config): bool|string
{
    $configPhpString = var_export($config, true);
    $configArrayString = "array(\n" . stripFirstAndLastLinesFromMultilineString($configPhpString) . "\n)";
    $configArray       = null; // To avoid PHPStorm 'undefined variable' warning below.

    eval("\$configArray = $configArrayString;");

    try {
        $configJson = json_encode($configArray, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return false;
    }

    return $configJson;
}

/**
 * @param $string
 * @return string
 */
function stripFirstAndLastLinesFromMultilineString($string): string
{
    $lines = explode("\n", $string);
    $linesToReturn = [];

    foreach ($lines as $lineNo => $line) {
        if ($lineNo === 0 || $lineNo === count($lines) - 1) {
            continue;
        }

        $linesToReturn[] = $line;
    }

    return implode("\n", $linesToReturn);
}
