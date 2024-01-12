<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Change ownership of Learnosity log.
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $logFile = '/var/log/schoolbox/' . INSTANCE_ID . '/learnosity-import.log';
        $this->getLogger()->info(
            sprintf(
                "Changing ownership of '%s' to '%s'",
                $logFile,
                CliTool\App::WEB_PROCESS_USER,
            )
        );
        chown($logFile, CliTool\App::WEB_PROCESS_USER);
        chgrp($logFile, CliTool\App::WEB_PROCESS_USER);
    }

    /**
     * This must be run as root, because it needs to change file permissions.
     */
    public function runAsRoot(): ?bool
    {
        return true;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO]))->runApp();
