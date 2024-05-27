<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * if staging, dev or trial, clear intellischool secret config.
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $dbc = $this->getDatabaseInternal();
        // if NOT prod
        if ('prod' !== $dbc->executeQuery("SELECT value FROM config WHERE name = 'site_type'")->fetchColumn()) {
            // update config
            $dbc->execute("UPDATE config SET value = '' WHERE name = 'intellischool_deployment_secret'");
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO], true))->runApp();
