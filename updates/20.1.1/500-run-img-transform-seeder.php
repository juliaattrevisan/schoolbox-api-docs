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
 * It's difficult to fork in PHP and have the parent process finished due to shared resources.
 * Instead, we'll just call an intermediate shell script that will fork for us and call the PHP tool.
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $logDir = $this->getDatabaseInternal()
                       ->executeQuery("SELECT value FROM config WHERE name = 'logs_dir'")
                       ->fetchColumn();

        $logFile     = rtrim($logDir, '/') . "/storage-transform-seeder.log";
        $toolScript = __DIR__ . '/../../tools/storage-transform-seeder.php';

        if($this->forkProcess("$toolScript -p", $logFile, $logFile, true)){
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();

