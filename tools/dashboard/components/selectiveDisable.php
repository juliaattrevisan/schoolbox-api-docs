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
        $components = implode(",", $this->dashboardComponentsToDisable);

        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

This tool disable potentially resource expensive components in dashboard with likes of

$components

that we can restore later.

Usage: INSTANCE_ID=vagrantx(optional) selectiveDisable.php


HELP;
            exit;
        }


        $this->instanceId = INSTANCE_ID;

        /* @var IConnector $db */
        $db = $this->getDi()->get('databaseInternal');

        /** @var IConfigLoader $config */
        $config = $this->getDi()->get('config');
        $backupFileName = $config->get('storage_dir') . '/modules.json';
        $components     = "'" . implode("','", $this->dashboardComponentsToDisable) . "'";

        $query = "SELECT * FROM component_definition WHERE name IN (" . $components . ") AND module = 'dashboard'";

        $this->getLogger()->notice('Saving dashboard components settings backup to: ' . $backupFileName);

        try {
            file_put_contents($backupFileName, json_encode($db->executeQuery($query)->fetchAll(), JSON_THROW_ON_ERROR));
        } catch (\Schoolbox\File\Exception $e) {
            $this->getLogger()->critical($e->getMessage());
            exit(1);
        }

        // Disable modules
        try {
            $this->getLogger()->notice('Disable dashboard components: ' . implode(",", $this->dashboardComponentsToDisable));
            $query = "UPDATE component_definition SET enabled = false WHERE name IN (" . $components . ") AND module = 'dashboard'";
            $db->execute($query);
        } catch (\Alaress\Database\Exception $e) {
            $this->getLogger()->critical($e->getMessage());
            exit(1);
        }
    }
};

$options = [CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
