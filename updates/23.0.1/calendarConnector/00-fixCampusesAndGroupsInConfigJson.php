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

Usage: php 00-fixCampusesAndGroupsInConfigJson.php

HELP;
            exit;
        }

        $dbc = $this->getDatabaseInternal();
        $logger = $this->getLogger();

        $logger->info('Getting calendar_connector Vcal rows...');
        $rows = $dbc->executeQuery('SELECT id, config FROM calendar_connector WHERE connector_class="Vcal"')->fetchAll();
        $logger->info('Found ' . count($rows) . " rows.");

        $logger->info("For each row in calendar_connector, updating `config` JSON data if required.");
        $logger->info("(changing 'campus' to 'campusIds', and 'groups' to 'groupIds')");
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

                $logger->debug("ID: {$row['id']}");
                $logger->debug("OLD JSON: $configOldJson");
                $logger->debug("NEW JSON: $configNewJson");
                $logger->debug("---");

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

        $logger->info("Done.  $nRowsUpdated row(s) updated.");
    }

    /**
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
