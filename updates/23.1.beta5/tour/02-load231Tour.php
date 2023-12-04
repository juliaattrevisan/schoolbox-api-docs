#!/usr/bin/php
<?php

/**
 * Install the 23.0 tour to advertise new features to staff
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Set colours to pastoral severity
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $plan = strtolower($this->getConfig()->get('site_plan');
        if ($plan === 'elite' || $plan === 'pro') {
            passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/231tour-lms.yaml', $returnVar);
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
