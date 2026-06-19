#!/usr/bin/env php
<?php

/**
 * Install the 26.1 tour to advertise new features to staff.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Install feature tours based on the current instance plan.
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/261_admin.yaml');

        switch (strtolower($this->getConfig()->get(ConfigMapper::NAME_SITE_PLAN))) {
            case 'learning-enhanced':
            case 'learning-essentials':
                passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/261_Learning.yaml');
                break;

            case 'canvas-elite':
            case 'canvas-enhanced':
                passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/261_Canvas.yaml');
                break;

            case 'community-elite':
            case 'community-enhanced':
            case 'community-essentials':
                passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/261_Community.yaml');
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
