#!/usr/bin/php
<?php

/**
 * Install the 24.0 tour to advertise new features to staff
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Set colours to pastoral severity
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        if ($this->getConfig()->get(ConfigMapper::NAME_LMS_ENABLED)) {
            passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/240tour-lms.yaml', $returnVar);
        } else {
            passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/240tour-non-lms.yaml', $returnVar);
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
