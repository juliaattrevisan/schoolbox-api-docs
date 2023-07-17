#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {
    public function runAsRoot(): ?bool
    {
        return true;
    }

    public function run(): void
    {
        foreach (['scheduled', 'routed', 'digestd', 'socketd'] as $service) {
            exec(sprintf('systemctl stop schoolbox-cloud-%s@%s', $service, INSTANCE_ID));
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
