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

Usage: php replace_serialized_calendar_config_with_json.php

HELP;
            exit;
        }

        $dbc = $this->getDatabaseInternal();

        echo 'Getting calendar_connector rows...';
        $rows = $dbc->executeQuery('SELECT id, config FROM calendar_connector')->fetchAll();
        echo 'done.  Found ' . count($rows) . " rows.\n\n";

        echo "For each row in calendar_connector, updating `config` JSON data.\n";
        echo "(changing 'campus' to 'campusIds', and 'groups' to 'groupIds')\n";
        foreach ($rows as $row) {
            $configOldJson = $row['config'];
            $configOld = json_decode($configOldJson, true, 512, JSON_THROW_ON_ERROR);
            $configNew = [];

            foreach ($configOld as $key => $value) {
                if ($key === 'groups') {
                    $key = 'groupIds';
                }

                if ($key === 'campus') {
                    $key = 'campusIds';
                }

                $configNew[$key] = $value;
            }

            $configNewJson = json_encode($configNew, JSON_THROW_ON_ERROR);

            printf(" * id: %3s, strlen(configOld): %4d, strlen(configNew): %4d\n", $row['id'], strlen($configOldJson), strlen($configNewJson));

            $query = (
                "UPDATE calendar_connector
                 SET config=:configNewJson
                 WHERE id=:id"
            );
            $statement = $dbc->prepareQuery($query, ['id' => $row['id'], 'configNewJson' => $configNewJson]);
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
