#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Application\CliTool;
use Schoolbox\IConfigLoader;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * A tool that will disable potentially resource expensive components in dashboard.
 *
 * This will generate a json file where it contains a lists of selected components with their current state for restoration.
 */
$app = new class extends CliTool\App
{
    /** @var string $instanceId */
    private $instanceId;

    /**
     * The selected list of modules to disable in dashboard
     *
     * @var array|string[]
     */
    private array $dashboardComponentsToDisable = [
        'Timetable',
        'Calendar',
        'News',
        'Upcoming Due Work',
        'Recent Submissions',
    ];

    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return true;
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

This tool will restore the modules that was disabled by disableSelectedDashboardComponents.php

Usage: INSTANCE_ID=vagrantx(optional) restore.php

HELP;
            exit;
        }

        $this->instanceId = INSTANCE_ID;

        /* @var IConnector $db */
        $db = $this->getDi()->get('databaseInternal');

        /** @var IConfigLoader $config */
        $config = $this->getDi()->get('config');
        $backupFileName = $config->get('stream_dir') . '/modules.json';

        try {
            $records = json_decode(file_get_contents($backupFileName), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Schoolbox\File\Exception $e) {
            $this->getLogger()->critical($e->getMessage());
            exit(1);
        }

        $updateQuery = [];

        foreach ($records as $record) {
            $updateQuery[] = "UPDATE component_definition SET enabled = " . $record['enabled'] . " WHERE id = " . $record['id'];
        }

        if (!empty($updateQuery)) {
            try {
                $this->getLogger()->notice('Restoring disabled dashboard components');
                $db->execute(implode(";", $updateQuery));
            } catch (\Alaress\Database\Exception $e) {
                $this->getLogger()->critical($e->getMessage());
                exit(1);
            }

            $this->getLogger()->notice('Restored');
        } else {
            $this->getLogger()->notice('Nothing to restore');
        }
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
