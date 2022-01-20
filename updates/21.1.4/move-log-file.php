#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Update log location to be instance specific location in /var/log/schoolbox
 */
$app = new class extends CliTool\App {

    /** Hands off execution over to the App */
    public function run(): void
    {
        $logsDir = sprintf("/var/log/schoolbox/%s", INSTANCE_ID);
        $this->getDatabaseInternal()->executeQuery(
            "UPDATE config SET value = :logs_dir WHERE name = 'logs_dir'",
            ['logs_dir' => $logsDir]
        );

        $this->getDatabaseInternal()->executeQuery(
            "UPDATE config SET value = 'purifier_cache_dir:purifier;twig_cache_dir:twig;backup_dir:files;zip_dir:cache/zip;stream_dir:cache/stream;portrait_dir:cache/portrait;storage_dir:storage' WHERE name = 'data_dirs_defaults'",
        );

        if (!is_dir($logsDir)) {
            if (!mkdir($logsDir, 0775, true) && !is_dir($logsDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $logsDir));
            }
            chown($logsDir, "www-data");
        }
    }

    public function runAsRoot(): ?bool
    {
        return true; // Need to create dir on disk. Puppet will do this, but this script may be run directly if puppet fails.
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
