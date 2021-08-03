#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Enables operator to enable/disable resource expensive dashboard components
 *
 * Stores backup files in JSON format in /etc/schoolbox dir
 */
$app = new class extends CliTool\App {

    private const MODE_SHOW    = 'show';
    private const MODE_BACKUP  = 'backup';
    private const MODE_MODIFY  = 'modify';
    private const MODE_RESTORE = 'restore';

    private const MODES = [
        1 => self::MODE_SHOW,
        2 => self::MODE_BACKUP,
        3 => self::MODE_MODIFY,
        4 => self::MODE_RESTORE,
    ];

    private const BACKUP_DIR         = "/etc/schoolbox"; // dir where module config backups are stored
    private const BACKUP_FILE_PREFIX = "dashboard-component-config"; // common prefix of backup files

    private const MODE_DESCRIPTIONS = [
        self::MODE_SHOW    => "Show current config enabled state",
        self::MODE_BACKUP  => "Backup current dashboard component enabled state to file",
        self::MODE_MODIFY  => "Enable/Disable dashboard components",
        self::MODE_RESTORE => "Restore dashboard component enabled state from file",
    ];


    /**
     * The selected list of modules to disable in dashboard
     *
     * @var array|string[]
     */
    private const DASHBOARD_COMPONENTS_TO_DISABLE = [
        'Timetable',
        'Calendar',
        'News',
        'Upcoming Due Work',
        'Recent Submissions',
    ];

    // --help: print help
    private const OPT_LONG_HELP = 'help';
    private const OPT_LONG_MODE = 'mode:';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_MODE,
        ];
    }


    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return true; // needs to write to '/etc/schoolbox/' dir
    }


    /** @inheritDoc */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit;
        }
        switch ($this->getMode()) {
            case self::MODE_SHOW:
                $this->showDashboardComponentConfig();
                break;
            case self::MODE_BACKUP:
                $this->backupDashboardComponentConfig();
                break;
            case self::MODE_MODIFY:
                $this->modifyDashboardComponentConfig();
                break;
            case self::MODE_RESTORE:
                $this->restoreDashboardComponentConfig();
                break;
        }
    }

    /**
     * Return's desired mode of tool action
     *
     * Uses CLI arg if provided, otherwise prompts user to select from choices
     *
     * @return string
     */
    private function getMode(): string
    {
        // Enable mode to be provided from command line
        $mode = $this->getOpt(self::OPT_LONG_MODE);
        if ($mode !== null) {
            if (in_array($mode, self::MODES, true)) {
                return $mode;
            }
            $this->getLogger()->error("Invalid mode: '$mode'");
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        $choice = $this->getChoice("Please select desired mode:", self::MODES);
        return self::MODES[$choice];
    }


    /**
     * Displays current dashboard component enabled/disabled state
     */
    private function showDashboardComponentConfig(): void
    {
        $this->getLogger()->notice("Current config:");
        foreach ($this->getComponentConfig() as $row) {
            $this->getLogger()->notice("Id: {$row['id']}, Name: {$row['name']}, Enabled: {$row['enabled']}");
        }
    }


    /**
     * Backup dashboard component enabled/disabled state to json file
     */
    private function backupDashboardComponentConfig(): void
    {
        $this->getLogger()->notice("Backing up config:");

        $data = [];
        foreach ($this->getComponentConfig() as $row) {
            $this->getLogger()->notice("Id: {$row['id']}, Name: {$row['name']}, Enabled: {$row['enabled']}");
            $data[$row['id']] = $row['enabled'];
        }

        $backupFileName = $this->generateBackupFileName();
        if (file_put_contents($backupFileName, json_encode($data, JSON_THROW_ON_ERROR)) === false) {
            $this->getLogger()->error("Unable to write to backup file '$backupFileName'");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        $this->getLogger()->notice("Backup written to file '$backupFileName'");
    }


    /**
     * Returns current component config from DB
     *
     * @return string[][]
     */
    private function getComponentConfig(): array
    {
        $db    = $this->getDatabaseInternal();
        $query = "
            SELECT id, name, enabled
            FROM component_definition
            WHERE name IN ('" . implode("','", self::DASHBOARD_COMPONENTS_TO_DISABLE) . "')
            AND module = 'dashboard'
        ";
        return $db->executeQuery($query)->fetchAll();
    }


    /**
     * Generates a backup file name
     *
     * @return string
     */
    private function generateBackupFileName(): string
    {
        return sprintf(
            "%s/%s-%s-%s.json",
            self::BACKUP_DIR,
            self::BACKUP_FILE_PREFIX,
            INSTANCE_ID,
            Alaress_Date_Format::now()->getUsingFormat("Y-m-d_H-i-s"),
        );
    }


    /**
     * Interactively enables/disables dashboard components
     */
    private function modifyDashboardComponentConfig(): void
    {
        $this->getLogger()->notice("Modify config:");
        $choices = [
            0 => 'disable',
            1 => 'enable',
        ];
        foreach ($this->getComponentConfig() as $row) {
            $choice = $this->getChoice("Name: '{$row['name']}', Enabled: {$row['enabled']}. Enable?", $choices);
            if ($choice === (int)$row['enabled']) {
                $this->getLogger()->debug("No change");
                continue;
            }
            $this->updateConfigEnabled((int)$row['id'], $row['name'], $choice);
        }
    }


    /**
     * Restores dashboard component enabled config from disk
     */
    private function restoreDashboardComponentConfig(): void
    {
        $this->getLogger()->notice("Restore config:");
        $backupFiles = glob(self::BACKUP_DIR . '/' . self::BACKUP_FILE_PREFIX . '*.json');
        if (count($backupFiles)) {
            // Convert 0-base index to 1-base
            $backupFiles = array_flip($backupFiles);
            $backupFiles = array_map(static function (int $idx): int {
                return $idx + 1;
            }, $backupFiles);
            $backupFiles = array_flip($backupFiles);
        }
        if (!count($backupFiles)) {
            $this->getLogger()->warning(sprintf('No backup files found in %s/', self::BACKUP_DIR));
            exit;
        }
        if (count($backupFiles) === 1) {
            $choice = 1;
        } else {
            $choice = $this->getChoice("Select a backup file to restore from:", $backupFiles);
        }
        $fileName = $backupFiles[$choice];

        $configBackup = json_decode(file_get_contents($fileName), true, 512, JSON_THROW_ON_ERROR);

        foreach ($this->getComponentConfig() as $row) {
            $this->getLogger()->notice("Name: '{$row['name']}', Enabled: {$row['enabled']}");
            if ($row['enabled'] === $configBackup[$row['id']]) {
                $this->getLogger()->notice("No change");
                continue;
            }
            $this->updateConfigEnabled((int)$row['id'], $row['name'], (bool)$configBackup[$row['id']]);
        }
    }


    /**
     * Enable/Disable the specified component
     *
     * @param int    $componentDefinitionId
     * @param string $name
     * @param bool   $enabled
     */
    private function updateConfigEnabled(int $componentDefinitionId, string $name, bool $enabled): void
    {
        $enabledValue = $enabled ? '1' : '0';
        try {
            $this->getDatabaseInternal()->execute("UPDATE component_definition SET enabled = $enabledValue WHERE id = $componentDefinitionId");
            $this->getLogger()->notice("Component '$name' set to '$enabledValue'");
        } catch (\Alaress\Database\Exception $e) {
            $this->getLogger()->error($e->getMessage());
        }
    }


    private function printHelp(): void
    {
        echo "NAME:\n";
        echo "\tDashboard Component Enable/Disable\n\n";
        echo "DESCRIPTION:\n\tThis tool enables you to manage whether specific dashboard components are enabled for this instance\n";
        echo "\tThe tool can be run in different modes:\n";

        foreach (self::MODES as $mode) {
            echo "\t  * $mode - " . self::MODE_DESCRIPTIONS[$mode] . "\n";
        }

        echo "USAGE:\n";
        echo self::COLOUR_GREEN . "\tINSTANCE_ID=[INSTANCE_ID] {$this->getToolFilename()} --mode [MODE] [--help]\n\n" . self::COLOUR_WHITE;

        echo "OPTIONS:\n";
        echo "\t--help           Print this help message and exit\n";
        $modeString = implode(',', self::MODES);
        echo "\t--mode [MODE]    One of [$modeString]\n";
    }
};


/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, [CliKernelAbstract::OPTION_ERRORS => E_ALL,]))->runApp();
