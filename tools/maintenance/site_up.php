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
        $log = $this->getLogger();
        $success = $dbc->execute('UPDATE config SET value = 0 WHERE name = "maintenance_mode"');

        if ($success) {
            $log->info('Maintenance mode disabled.');
        } else {
            $log->info('Maintenance mode could not be disabled.');
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO]))->runApp();
