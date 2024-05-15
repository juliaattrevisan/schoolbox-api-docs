<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {
    public function run(): void
    {
        $dbc = $this->getDatabaseInternal();
        $success = $dbc->execute('UPDATE config SET value = 1 WHERE name = "maintenance_mode"');

        # IMPORTANT - IF YOU CHANGE THIS FILE, YOU MUST PORT THE SAME LOGICAL CHANGE TO upgrade-instances.sh
        if ($success) {
            echo "Maintenance mode enabled.\n";
        } else {
            echo "Maintenance mode could not be enabled.\n";
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO]))->runApp();
