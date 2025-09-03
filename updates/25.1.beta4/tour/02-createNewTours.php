#!/usr/bin/php
<?php

/**
 * Install the 25.1 tour to advertise new features to staff
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Install the 25.1 tour to advertise new features to users.
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/251tour_dashboard.yaml');
        passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/251tour_pastoral_dashboard.yaml');
        passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/251tour_course_markbook.yaml');
        passthru('php ' . __DIR__ . '/../../../tools/tour/install.php --perform ' . __DIR__ . '/251tour_class_page.yaml');
    }

    public function runAsRoot(): ?bool
    {
        return null;
    }
};

(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
