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
        $this->getDatabaseInternal()->executeQuery(
            "UPDATE config SET value = CONCAT('/var/log/schoolbox/',:instance) WHERE name like 'logs_dir'",
            ['instance'=>INSTANCE_ID]
        );

        $this->getDatabaseInternal()->executeQuery(
            "UPDATE config SET value = 'purifier_cache_dir:purifier;twig_cache_dir:twig;backup_dir:files;zip_dir:cache/zip;stream_dir:cache/stream;portrait_dir:cache/portrait;storage_dir:storage' WHERE name like 'data_dirs_defaults'",
        );

    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
