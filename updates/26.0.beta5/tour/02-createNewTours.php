#!/usr/bin/env php
<?php

/**
 * Install the 26.0 tour to advertise new features to staff
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Install the 25.1 tour to advertise new features to users.
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        switch (strtolower($this->getConfig()->get(ConfigMapper::NAME_SITE_PLAN))) {
            case 'canvas-elite':
            case 'canvas-enhanced':
            case 'community-elite':
            case 'community-enhanced':
                passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/260_ResourceBooking.yaml');
                break;

            case 'learning-enhanced':
                passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/260_All.yaml');
                break;

            case 'community-essentials':
            case 'learning-essentials':
                passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/260_OLC-only.yaml');
                break;
            default:
                $this->getLogger()->warning('Invalid Plan name');
                break;
        }
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
