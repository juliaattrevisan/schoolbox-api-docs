#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Simple app that forks.
 * * Parent just returns immediately to not block upgrade
 * * Child forks off and asynchronously invokes the transform seeder to run in background
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $logDir   = $this->getDatabaseInternal()
                         ->executeQuery("SELECT value FROM config WHERE name = 'logs_dir'")
                         ->fetchColumn();
        $logFile  = rtrim($logDir, '/') . "/storage-transform-seeder.log";
        $fileName = __DIR__ . '/../../tools/storage-transform-seeder.php';
        $cmd      = "$fileName --perform -vvv 2>> $logFile";

        $this->getLogger()->notice("Executing command in async child process: '$cmd'");

        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->getLogger()->error("Error - Unable to fork");
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }
        if ($pid) {
            $this->getLogger()->debug("Parent process exiting");
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }

        // Child process
        exec($cmd);
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();

