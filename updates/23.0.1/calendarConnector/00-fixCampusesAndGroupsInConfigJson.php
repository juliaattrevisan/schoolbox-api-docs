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
        return [self::OPT_LONG_HELP];
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Fix groupIds and campusIds JSON data in schoolbox.calendar_connector.config.

This script will update schoolbox.calendar_connector.config so that JSON keys previously named 'campus' will be renamed
to 'campusIds', and keys previously named 'groups' will be renamed to 'groupIds'.

The 'campus' and 'groups' keys were incorrectly created by updates/23.0.alpha1/calendarConnector/02-migrateData.php
(SA-14746).

Usage: php replace_serialized_calendar_config_with_json.php

HELP;
            exit;
        }

        $dbc = $this->getDatabaseInternal();

        echo 'Getting calendar_connector rows...';
        $rows = $dbc->executeQuery('SELECT id, config FROM calendar_connector')->fetchAll();
        echo 'done.  Found ' . count($rows) . " rows.\n\n";

        echo "For each row in calendar_connector, updating `config` JSON data if required.\n";
        echo "(changing 'campus' to 'campusIds', and 'groups' to 'groupIds')\n";
        $nRowsUpdated = 0;
        foreach ($rows as $row) {
            $configOldJson = $row['config'];
            $configOld = json_decode($configOldJson, true, 512, JSON_THROW_ON_ERROR);
            $configNew = [];

            $updateRequired = false;

            foreach ($configOld as $key => $value) {
                if ($key === 'groups') {
                    $key = 'groupIds';
                    $updateRequired = true;
                }

                if ($key === 'campus') {
                    $key = 'campusIds';
                    $updateRequired = true;
                }

                $configNew[$key] = $value;
            }

            if ($updateRequired) {
                $configNewJson = json_encode($configNew, JSON_THROW_ON_ERROR);

                printf(" * id: %3s, strlen(configOld): %4d, strlen(configNew): %4d\n", $row['id'], strlen($configOldJson), strlen($configNewJson));

                $query = (
                    "UPDATE calendar_connector
                     SET config=:configNewJson
                     WHERE id=:id"
                );
                $statement = $dbc->prepareQuery($query, ['id' => $row['id'], 'configNewJson' => $configNewJson]);
                $statement->execute();

                ++$nRowsUpdated;
            }
        }

        echo "\nDone.  $nRowsUpdated row(s) updated.\n";
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
