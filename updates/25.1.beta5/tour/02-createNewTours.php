#!/usr/bin/php
<?php

/**
 * Install the 25.1 tour to advertise new features to staff
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
        $planHasLmsEnabled = in_array(
            $this->getConfig()->get(ConfigMapper::NAME_SITE_PLAN),
            [
                // These plans have config 'lms_enabled' = 1.
                'canvas-enhanced',
                'learning-enhanced',
                'learning-essentials',
            ]
        );

        if (
            in_array(
                $this->getConfig()->get(ConfigMapper::NAME_SITE_PLAN),
                [
                    // These plans have config 'learning_moments_enabled' = 1.
                    'canvas-elite',
                    'canvas-enhanced',
                    'community-elite',
                    'community-enhanced',
                    'learning-enhanced',
                    'learning-essentials',
                ]
            )
        ) {
            if ($planHasLmsEnabled) {
                passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/251tour_dashboard_yLM.yLMS.yaml');
            } else {
                passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/251tour_dashboard_yLM_nLMS.yaml');
            }
        } else {
            if ($planHasLmsEnabled) {
                passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/251tour_dashboard_nLM_yLMS.yaml');
            } else {
                passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/251tour_dashboard_nLM_nLMS.yaml');
            }
        }

        if (
            in_array(
                $this->getConfig()->get(ConfigMapper::NAME_SITE_PLAN),
                [
                    // These plans have config 'pastoral_enabled' = 1.
                    'canvas-elite',
                    'community-elite',
                    'learning-enhanced',
                ]
            )
        ) {
            passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/251tour_pastoral_dashboard.yaml');
        }

        if ($planHasLmsEnabled) {
            passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/251tour_course_markbook.yaml');
        }

        passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/251tour_admin_home.yaml');
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
